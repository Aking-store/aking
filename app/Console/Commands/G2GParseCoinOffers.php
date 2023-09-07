<?php

namespace App\Console\Commands;

use App\Models\DumpGames;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;

class G2GParseCoinOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:g2g-parse-coin-offers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse and store coin offers(without updating prices)';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $client = new Client([
            'base_uri' => 'https://www.g2g.com',
            'timeout' => 15,
            'connect_timeout' => 15,
            'read_timeout' => 15,
        ]);

        $headers = [
            'cookies' => CookieJar::fromArray(loadG2GCookie(), '.www.g2g.com'),
        ];

        $response = $client->request('GET', '/sell/manage?service=16', $headers);
        $dom = new Dom();
        $dom->loadStr($response->getBody()->getContents());

        $games = [];
        foreach ($dom->find('ul.search-results__list.search-action-content-row > li') as $link) {
            $games[] = [
                'name' => $link->find('div.gameName')->text(true),
                'link' => $link->find('a')[0]->getAttribute('href'),
                'items' => []
            ];
        }
        $servers = [];

        foreach ($games as &$game) {
            parse_str(parse_url($game['link'], PHP_URL_QUERY), $parsed);
            $requestUrl = 'https://sls.g2g.com/offer/search?service_id=lgc_service_1&brand_id=lgc_game_' . $parsed['game'] . '&sort=recommended&page_size=100&currency=USD&country=UA';
            try {
                for ($page = 1; $page <= 1000; $page++) {
                    $response = $client->request('GET', $requestUrl . '&page=' . $page);
                    $categoryInfo = json_decode($response->getBody()->getContents(), true);

                    foreach ($categoryInfo['payload']['results'] as $server) {
                        $servers[$server['title']] = [
                            'title' => $server['title'],
                            'offer_group' => ltrim($server['offer_group'], '/'),
                            'brand_id' => $server['brand_id'],
                            'service_id' => $server['service_id'],
                            'collection_id' => $server['offer_attributes'][0]['collection_id'],
                            'region_id' => $server['region_id'],
                        ];
                    }
                }
            } catch (\Exception $exception) {
            }

            $response = $client->request('GET', $game['link'], $headers);
            $dom = new Dom();
            $dom->loadStr($response->getBody()->getContents());

            if (count($dom->find('div.manage__table'))) {
                $this->operateTable($dom, $game, $servers);
            } else {
                $this->regionsEnumeration($dom, $game, $servers, $client, $headers);
            }

            $this->loadFromDB($game);
        }
        dump($games);
    }

    /**
     * @param Dom $dom
     * @param array $game
     * @param array $servers
     * @param string $region
     * @return void
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    private function operateTable(Dom $dom, array &$game, array $servers = [], string $region = ''): void
    {
        foreach ($dom->find('div.manage__table tr') as $item) {
            try {
                if (count($item->find('span.products__num'))) {
                    $server = $servers[trim($item->find('span.products__title a.g2g_products_title')[0]->text())];
                    $game['items'][] = [
                        'id' => ltrim($item->find('span.products__num')[0]->text(), '#'),
                        'name' => $item->find('span.products__title a.g2g_products_title')[0]->text(),
                        'my_price' => $item->find('td.manage__table-actions a.g2g_products_price')[0]->text(),
                        'link' => 'https://www.g2g.com/offer/' . Str::slug($item->find('span.products__title a.g2g_products_title')[0]->text(), '-') . '?service_id=' . $server['service_id'] . '&brand_id=' . $server['brand_id'] . '&fa=' . $server['collection_id'] . ':' . $server['offer_group'] . '&sort=lowest_price&include_offline=1',
                        'link2' => 'https://www.g2g.com/checkout/buyNow/offerList?service_id=' . $server['service_id'] . '&brand_id=' . $server['brand_id'] . '&fa=' . $server['collection_id'] . ':' . $server['offer_group'] . '&sort=lowest_price&include_offline=1&offer_title=' . rawurlencode($server['title']) . '&group=0&load_type=offer&offer_sorting=lowest_price&offer_online_status=1&total_offer=25',
                        'server' => $server,
                        'region' => $region,
                        'min_stock' => '',
                        'max_stock' => '',
                        'dump' => '',
                        'current_lowest_price' => '',
                        'our_price' => '',
                        'our_new_price' => '',
                        'min_price' => '',
                        'checked' => rand(0, 1),
                    ];
                }
            } catch (\Exception $exception) {
            }
        }
    }

    /**
     * @param Dom $mainDom
     * @param array $game
     * @param array $servers
     * @param $client
     * @param array $headers
     * @return void
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws NotLoadedException
     * @throws StrictException
     */
    private function regionsEnumeration(Dom $mainDom, array &$game, array $servers = [], $client = null, array $headers = []): void
    {
        $regions = [];
        if ($mainDom->find('#region', 0)) {
            foreach ($mainDom->find('#region option') as $option) {
                if (!empty($option->getAttribute('value'))) {
                    $regions[] = [
                        'title' => $option->text(),
                        'value' => $option->getAttribute('value'),
                    ];
                }
            }
            foreach ($regions as $region) {
                $response = $client->request('GET', $game['link'] . '&region=' . $region['value'], $headers);
                $dom = new Dom();
                $dom->loadStr($response->getBody()->getContents());
                $this->operateTable($dom, $game, $servers, $region['title']);
            }
        }
    }

    /**
     * @param array $game
     * @return void
     */
    private function loadFromDB(array &$game): void
    {
        if (isset($game['items']) and !empty($game['items'])) {
            foreach ($game['items'] as &$item) {
                $dumpGame = DumpGames::firstOrCreate(
                    ['outer_id' => $item['id']],
                    [
                        'game_name' => $game['name'],
                        'name' => $item['name'],
                        'region' => $item['region'],
                        'link' => $item['link'],
                        'link2' => $item['link2'],
                    ]
                );
                $item['min_stock'] = $dumpGame->min_stock;
                $item['max_stock'] = $dumpGame->max_stock;
                $item['dump'] = $dumpGame->dump;
                $item['competitor_current_lowest_price'] = $dumpGame->current_price . '(' . $dumpGame->updated_at?->diffForHumans() . ' )';
                $item['our_price'] = $dumpGame->new_price . '(' . $dumpGame->our_price_updated_at?->diffForHumans() . ' )';
                $item['min_price'] = $dumpGame->min_price;
            }
        }
    }
}
