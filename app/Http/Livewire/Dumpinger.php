<?php

namespace App\Http\Livewire;

use App\Models\DumpGames;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Livewire\Component;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;
use Illuminate\Support\Str;

class Dumpinger extends Component
{
    public string $cookie = '';
    public bool $checkAll = false;
    public array $games = [];

    private Client $client;
    private array $headers = [];

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client([
            'base_uri' => 'https://www.g2g.com',
            'timeout' => 15,
            'connect_timeout' => 15,
            'read_timeout' => 15,
        ]);

        $this->headers = [
            'cookies' => CookieJar::fromArray([
                '_dd_s' => 'rum=0&expire=1689275067779',
                'active_device_token' => '231c6871ec11c3c230a2fd0475a9d53a',
                'g2g_regional' => '%7B%22country%22%3A%22BY%22%2C%22currency%22%3A%22USD%22%2C%22language%22%3A%22en%22%7D',
                'G2GSESID_V4' => 'p5ikgridc1s830cjuo4rimi813',
                'g_state' => '{"i_l":0}',
                'googtransopt' => 'os=1',
                'history_offers' => '%5B%2242362088%22%2C%2235587088%22%2C%2257135554%22%2C%2235589358%22%2C%2257135681%22%2C%2245586800%22%2C%2259530413%22%2C%2259583381%22%2C%2259292987%22%2C%2259292986%22%2C%2259293073%22%2C%2259530351%22%2C%2259293054%22%2C%2259293081%22%2C%2259338755%22%2C%2259338792%22%2C%2262355808%22%2C%2261956237%22%5D',
                'long_lived_token' => 'aae9a819b92af67bafa1cd82b673f9d7',
                'noticebar_cookie' => '1',
                'refresh_token' => '6955094.b7975add8eb7706bd83773210e3ccb6b',
                'YII_CSRF_TOKEN' => '4f661e59f7cc165f2b9a3d7bdf44a62922ad619c',
            ], '.www.g2g.com'),
        ];
    }

    /**
     * @return void
     */
    public function mount()
    {
    }

    /**
     * @return void
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws GuzzleException
     * @throws NotLoadedException
     * @throws StrictException
     */
    public function start(): void
    {
        $response = $this->client->request('GET', '/sell/manage?service=16', $this->headers);
        $dom = new Dom();
        $dom->loadStr($response->getBody()->getContents());

        $this->games = [];
        foreach ($dom->find('ul.search-results__list.search-action-content-row > li') as $link) {
            $this->games[] = [
                'name' => $link->find('div.gameName')->text(true),
                'link' => $link->find('a')[0]->getAttribute('href'),
                'items' => []
            ];
        }
        $servers = [];

        foreach ($this->games as &$game) {
            parse_str(parse_url($game['link'], PHP_URL_QUERY), $parsed);
            $requestUrl = 'https://sls.g2g.com/offer/search?service_id=lgc_service_1&brand_id=lgc_game_' . $parsed['game'] . '&sort=recommended&page_size=100&currency=USD&country=UA';
            try {
                for ($page = 1; $page <= 1000; $page++) {
                    $response = $this->client->request('GET', $requestUrl . '&page=' . $page);
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

            $response = $this->client->request('GET', $game['link'], $this->headers);
            $dom = new Dom();
            $dom->loadStr($response->getBody()->getContents());

            if (count($dom->find('div.manage__table'))) {
                $this->operateTable($dom, $game, $servers);
            } else {
                $this->regionsEnumeration($dom, $game, $servers);
            }

            $this->loadFromDB($game);
        }
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
     * @return void
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws GuzzleException
     * @throws NotLoadedException
     * @throws StrictException
     */
    private function regionsEnumeration(Dom $mainDom, array &$game, array $servers = []): void
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
                $response = $this->client->request('GET', $game['link'] . '&region=' . $region['value'], $this->headers);
                $dom = new Dom();
                $dom->loadStr($response->getBody()->getContents());
                $this->operateTable($dom, $game, $servers, $region['title']);
            }
        }
    }

    public function render()
    {
        return view('livewire.dumpinger');
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

    public function updated($name, $value)
    {
        $path = explode('.', $name);
        if ($path[0] == 'games') {
            $game = &$this->games[$path[1]];
            if ($path[2] == 'items') {
                $item = &$game['items'][$path[3]];

                $dumpGame = DumpGames::firstOrCreate(
                    ['outer_id' => $item['id']],
                    [
                        'game_name' => $game['name'],
                        'name' => $item['name'],
                        'region' => $item['region'],
                    ]
                );

                $dumpGame->{$path[4]} = $value;
                $dumpGame->save();
            }
        }
    }

    public function foo($index)
    {
        if (!empty($this->games)) {
            if (!empty($this->games[$index]['items'])) {
                foreach ($this->games[$index]['items'] as &$item) {
                    $item['our_price'] = rand(1, 100);
                }
            }
        }
    }
}
