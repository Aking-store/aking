<?php

namespace App\Http\Livewire;

use DiDom\Document;
use DiDom\Exceptions\InvalidSelectorException;
use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Shuchkin\SimpleXLSXGen;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Parser extends Component
{
    public array $names = [];
    public string $url = '';
    private Client $client;
    const ELDORADO_OFFER_TYPE = [
        'g' => 'Currency',
        'a' => 'Account',
        'i' => 'CustomItem',
    ];

    /**
     * @param $id
     */
    public function __construct($id = null)
    {
        parent::__construct($id);

        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 15,
            'read_timeout' => 15,
        ]);
    }

    /**
     * @return void
     */
    public function mount(): void
    {
    }

    /**
     * @return StreamedResponse
     * @throws GuzzleException
     * @throws InvalidSelectorException
     */
    public function parse()
    {
        if (App::environment('prod')) {
            Log::channel('telegram')->notice('Trying to parse - ' . $this->url);
        }
        $parsed = parse_url($this->url);

        if ($parsed['host'] == "www.g2g.com") {
            [$xlsx, $name] = $this->getFromG2G($parsed);
        } elseif (($parsed['host'] == "funpay.com")) {
            [$xlsx, $name] = $this->getFromFunPay($parsed);
        } elseif (($parsed['host'] == "www.eldorado.gg")) {
            [$xlsx, $name] = $this->getFromEldorado($parsed);
        }

        return response()->streamDownload(function () use ($xlsx) {
            echo $xlsx;
        }, $name);
    }

    /**
     * @return array|false|Application|Factory|View|\Illuminate\Foundation\Application|mixed
     */
    public function render()
    {
        return view('livewire.parser');
    }

    /**
     * @param $parsed
     * @return array
     * @throws GuzzleException
     */
    private function getFromG2G($parsed): array
    {
//        $response = $this->client->request('GET', 'https://assets.g2g.com/offer/keyword.json');
//
//        $games = json_decode($response->getBody()->getContents(), true);

        $response = $this->client->request('GET', 'https://assets.g2g.com/offer/categories.json');
        $categories = json_decode($response->getBody()->getContents(), true);
        $categories = array_filter($categories, fn($n) => count($n) == 4);

        $name = '';
        $xlsx = SimpleXLSXGen::fromArray([]);
        foreach ($categories as $key => $category) {
            if (!str_contains($key, explode('/', $parsed['path'])[2])) {
                continue;
            }
            $name = $key;
            try {
                $response = $this->client->request(
                    'GET',
                    'https://sls.g2g.com/offer/keyword_relation/collection?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id']
                );
                $dirtAttributes = json_decode($response->getBody()->getContents(), true);
                $attributes = [];
                $subAttributes = [];
                foreach ($dirtAttributes['payload']['results'] as $attribute) {
                    $attributes[$attribute['collection_id']] = $attribute['label']['en'];
                    foreach ($attribute['children'] as $child) {
                        if (count($child['children']) > 1) {
                            foreach ($child['children'] as $subChild) {
                                $subAttributes[$subChild['dataset_id']] = $child['value'];
                            }
                            $subAttributes[$child['dataset_id']] = $child['value'];
                        }
                    }
                }
            } catch (\Exception $exception) {
                $attributes = false;
            }

            $requestUrl = 'https://sls.g2g.com/offer/search?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id'] . '&sort=recommended&page_size=100&currency=USD&country=UA';

            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                if (isset($query['region_id'])) {
                    $requestUrl = $requestUrl . '&region_id=' . $query['region_id'];
                }
                if (isset($query['fa'])) {
                    $requestUrl = $requestUrl . '&filter_attr=' . $query['fa'];
                }
                if (isset($query['seller'])) {
                    $requestUrl = $requestUrl . '&seller=' . $query['seller'];
                }
            }

            $array[] = [
                'title',
                'price',
                'score',
                'seller_outer_name',
                'updated'
            ];

            try {
                for ($page = 1; $page <= 1000; $page++) {
                    $response = $this->client->request('GET', $requestUrl . '&page=' . $page);
                    $categoryInfo = json_decode($response->getBody()->getContents(), true);
                    foreach ($categoryInfo['payload']['results'] as $categoryProduct) {
                        $productAttributes = [];
                        if ($attributes) {
                            foreach ($categoryProduct['offer_attributes'] as $attribute) {
                                if (isset($subAttributes[$attribute['dataset_id']])) {
                                    $productAttributes['s-' . $subAttributes[$attribute['dataset_id']]] = $subAttributes[$attribute['dataset_id']] . ' > ' . $attribute['value'];
                                } elseif (isset($attributes[$attribute['collection_id']])) {
                                    $productAttributes['a-' . $attributes[$attribute['collection_id']]] = $attribute['value'];
                                } else {
                                    $productAttributes['e-' . $attribute['collection_id']] = $attribute['value'];
                                }
                            }
                        } else {
                            foreach ($categoryProduct['offer_attributes'] as $attribute) {
                                $productAttributes[$attribute['collection_id']] = $attribute['value'];
                            }
                        }

                        $array[] = [
                            'title' => trim($categoryProduct['title']),
                            'price' => $categoryProduct['display_price'],
                            'score' => $categoryProduct['score'],
                            'seller_outer_name' => $categoryProduct['username'],
                            'updated' => date("Y-m-d H:i:s", $categoryProduct['updated_at'] / 1000),
                            ... $productAttributes
                        ];
                    }
                }
            } catch (\Exception $exception) {
            }

            $xlsx = SimpleXLSXGen::fromArray($array);
        }
        return [$xlsx, $name . ' - G2G - ' . date("Y-m-d H-i-s") . '.xls'];
    }

    /**
     * @param $parsed
     * @return array
     * @throws GuzzleException
     * @throws InvalidSelectorException
     */
    private function getFromFunPay($parsed): array
    {
        $response = $this->client->request('GET', $this->url, [
            'cookies' => CookieJar::fromArray([
                'cy' => "usd"
            ], $parsed['host']),
        ]);

        $html = $response->getBody()->getContents();

        $document = new Document();
        $document->loadHtml($html);

        $array = [];

        try {
            foreach ($document->find('a.tc-item') as $element) {
                $item = [];

                foreach ($element->find('div') as $subElement) {
                    if ($subElement->parent()->classes()->contains('tc-item')) {
                        $item = [...$item, ...$this->funPayGetTextFromItem($subElement)];
                    }
                }
                $item[] = (string)$element->getAttribute('href');
                $array[] = $item;
            }
        } catch (ErrorException|\Exception $exception) {
            dd($exception->getMessage());
        }
        $name = $document->find('div h1')[0]->text();
        $xlsx = SimpleXLSXGen::fromArray($array);

        return [$xlsx, $name . ' - FunPay - ' . date("Y-m-d H-i-s") . '.xls'];
    }

    private function funPayGetTextFromItem($element): array
    {
        return match ($element->getAttribute('class')) {
            'tc-user' => [
                trim($element->find('div.media-user-name')[0]->text()),
                count($element->find('div.rating-stars')) != 0 ? str_replace('rating-stars rating-','',trim($element->find('div.rating-stars')[0]->getAttribute('class'))) . ' звёзд' : 'нет отзывов',
                count($element->find('span.rating-mini-count')) != 0 ? trim($element->find('span.rating-mini-count')[0]->text()) . ' отзывов' : 'нет отзывов',
            ],
            'tc-desc' => [trim($element->find('div.tc-desc-text')[0]->text())],
            default => [trim($element->text())],
        };
    }

    private function getFromEldorado($parsed): array
    {
        $parsed['path'] = explode('/', $parsed['path']);
//        dump($parsed);
        $response = $this->client->request('GET', $this->url);
        $html = $response->getBody()->getContents();
        $document = new Document();
        $document->loadHtml($html);
        $array = [];


        if (count($parsed['path']) == 4) {
            $name = $document->find('div.game-header div.name')[0]->text();
            $requestUrl = 'https://www.eldorado.gg/api/flexibleOffers/?itemTreeId=' . $parsed['path'][3] . '&offerType=' . self::ELDORADO_OFFER_TYPE[$parsed['path'][2]];
            if (isset($parsed['query'])) {
                $requestUrl .= '&' . $parsed['query'];

                parse_str($parsed['query'], $query);
                if (isset($query['hotSearchQuery'])) {
                    $requestUrl = $requestUrl . '&searchQuery=' . $query['hotSearchQuery'];
                    unset($query['hotSearchQuery']);
                }
                if (isset($query['te_v0'])) {
                    $requestUrl = $requestUrl . '&tradeEnvironmentValue0=' . $query['te_v0'];
                    unset($query['te_v0']);
                }
                if (isset($query['attr_ids'])) {
                    $requestUrl = $requestUrl . '&offerAttributeIdsCsv=' . $query['attr_ids'];
                    unset($query['attr_ids']);
                }

                unset($query['gamePageOfferIndex']);
                unset($query['gamePageOfferSize']);
                foreach ($query as $key => $value) {
                    $requestUrl = $requestUrl . '&' . $key . '=' . $value;
                }
            }
            $requestUrl .= '&pageSize=50&pageIndex=';

            try {
                for ($page = 1; $page <= 1000000; $page++) {
                    $response = $this->client->request('GET', $requestUrl . $page, [
                        'cookies' => CookieJar::fromArray([
                            'eldoradogg_currencyPreference' => "USD"
                        ], $parsed['host']),
                    ]);

                    $offers = json_decode($response->getBody()->getContents(), true);
                    $offers = $offers['results'];

                    if (count($offers)) {
                        foreach ($offers as $offer) {
                            $array[] = [
                                'title' => trim($offer['offer']['offerTitle']),
                                'price' => trim(
                                    $offer['offer']['pricePerUnit']['amount'] . $offer['offer']['pricePerUnit']['currency']
                                ),
                                'seller_outer_name' => trim($offer['user']['username']),
                                'url' => 'https://www.eldorado.gg/' . $parsed['path'][1] . '/o' . $parsed['path'][2] . '/' . $offer['offer']['id'],
                            ];
                        }
                    } else {
                        break;
                    }
                }
            } catch (\Exception $exception) {
                dd($exception->getMessage());
            }
        } elseif (count($parsed['path']) == 3 and $parsed['path'][1] == 'users') {
            $name = $parsed['path'][2];

            $requestUrl = 'https://www.eldorado.gg/api/users/MonkeyGaming/publicByUsername/';
            $response = $this->client->request('GET', $requestUrl);
            $user = json_decode($response->getBody()->getContents(), true);

            $requestUrl = 'https://www.eldorado.gg/api/flexibleOffers/user/' . $user['id'] . '/?';

            parse_str($parsed['query'], $query);

            $urlTreeTypePart = array_search($query['itemTreeType'], self::ELDORADO_OFFER_TYPE);

            unset($query['tab']);
            unset($query['pageIndex']);
            unset($query['itemTreeType']);

            foreach ($query as $key => $value) {
                $requestUrl .= '&' . $key . '=' . $value;
            }
            $requestUrl .= '&pageSize=50&pageIndex=';

            try {
                for ($page = 1; $page <= 1000000; $page++) {
                    $response = $this->client->request('GET', $requestUrl . $page, [
                        'cookies' => CookieJar::fromArray([
                            'eldoradogg_currencyPreference' => "USD"
                        ], $parsed['host']),
                    ]);

                    $offers = json_decode($response->getBody()->getContents(), true);
//                    dump($offers);
                    $offers = $offers['results'];

                    if (count($offers)) {
                        foreach ($offers as $offer) {
                            $url = '';
                            if ($urlTreeTypePart and isset(end($offer['itemHierarchy'])['seoAlias'])) {
                                $url = 'https://www.eldorado.gg/' . end($offer['itemHierarchy'])['seoAlias'] . '/o' . $urlTreeTypePart . '/' . $offer['id'];
                            }

                            $array[] = [
                                'title' => trim($offer['offerTitle']),
                                'price' => trim(
                                    $offer['pricePerUnit']['amount'] . $offer['pricePerUnit']['currency']
                                ),
                                'seller_outer_name' => $name,
                                'url' => $url,
                            ];
                        }
                    } else {
                        break;
                    }
                }
            } catch (\Exception $exception) {
                dd($exception->getMessage());
            }
        } else {
            $name = 'Empty';
        }

        $xlsx = SimpleXLSXGen::fromArray($array);
        return [$xlsx, $name . ' - Eldorado - ' . date("Y-m-d H-i-s") . '.xls'];
    }
}
