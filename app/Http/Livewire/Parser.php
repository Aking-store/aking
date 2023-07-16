<?php

namespace App\Http\Livewire;

use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use PHPHtmlParser\Dom;
use App\Iteration;
use Livewire\Component;
use App\Product;
use Illuminate\Database\Eloquent\Collection;
use Shuchkin\SimpleXLSXGen;

class Parser extends Component
{
    public $names = [];
    public $url = '';

    public function mount()
    {
    }

    public function csv($name)
    {
        $iteration = Iteration::max('id');
        $clearProductsArray = [];
        $titles = [];
        Product::where('game_outer_name', $name)->where('iteration', '>', $iteration - 7)->chunk(200, function (Collection $products) use (&$clearProductsArray, &$titles) {
            foreach ($products as $product) {
                $productClear = $product->toArray();
                unset(
                    $productClear['id'],
                    $productClear['iteration'],
                    $productClear['offer_id'],
                    $productClear['seller_outer_id'],
                    $productClear['game_outer_id'],
                    $productClear['game_outer_name'],
                    $productClear['category_outer_id'],
                    $productClear['data'],
                    $productClear['created_at'],
                    $productClear['updated_at'],
                    $productClear['category_outer_name'],
                    $productClear['site_name'],
                    $productClear['prices'],
                );
                foreach ($productClear as $key => $value) {
                    $titles[$key] = $key;
                }
                $clearProductsArray[] = $productClear;
            }
        });
        $array = [];
        $array[] = $titles;
        foreach ($titles as $key => &$value) {
            $value = '';
        }
        foreach ($clearProductsArray as $clearProduct) {
            $array[] = array_merge($titles, $clearProduct);
        }
        $xlsx = SimpleXLSXGen::fromArray($array);

        return response()->streamDownload(function () use ($xlsx) {
            echo (string)$xlsx;
        }, $name . ' - G2G - ' . date("Y-m-d H-i-s") .'.xls');
    }

    public function parse()
    {
        $parsed = parse_url($this->url);

        $client = new Client([
            'timeout' => 15,
            'connect_timeout' => 15,
            'read_timeout' => 15,
        ]);

        if ($parsed['host'] == "www.g2g.com") {
            $response = $client->request('GET', 'https://assets.g2g.com/offer/keyword.json');

            $games = json_decode($response->getBody()->getContents(), true);

            $response = $client->request('GET', 'https://assets.g2g.com/offer/categories.json');
            $categories = json_decode($response->getBody()->getContents(), true);
            $categories = array_filter($categories, fn ($n) => count($n) == 4);

            $name = '';
            foreach ($categories as $key => $category) {
                if (!str_contains($key, explode('/', $parsed['path'])[2])) {
                    continue;
                }
                $name = $key;
                try {
                    $response = $client->request('GET', 'https://sls.g2g.com/offer/keyword_relation/collection?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id']);
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
                        $response = $client->request('GET', $requestUrl . '&page=' . $page);
                        $categoryInfo = json_decode($response->getBody()->getContents(), true);
                        foreach ($categoryInfo['payload']['results'] as $categoryProduct) {
                            $productAttributes = [];
                            if ($attributes) {
                                foreach ($categoryProduct['offer_attributes'] as $attribute) {
                                    if (isset($subAttributes[$attribute['dataset_id']])) {
                                        $productAttributes['s-'.$subAttributes[$attribute['dataset_id']]] = $subAttributes[$attribute['dataset_id']] . ' > ' .$attribute['value'];
                                    } elseif (isset($attributes[$attribute['collection_id']])) {
                                        $productAttributes['a-'.$attributes[$attribute['collection_id']]] = $attribute['value'];
                                    } else {
                                        $productAttributes['e-'.$attribute['collection_id']] = $attribute['value'];
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

                return response()->streamDownload(function () use ($xlsx) {
                    echo (string)$xlsx;
                }, $name . ' - G2G - ' . date("Y-m-d H-i-s") .'.xls');
            }
        } elseif (($parsed['host'] == "funpay.com")) {
            $response = $client->request('GET', $this->url, [
                'cookies' => CookieJar::fromArray([
                    'cy' => "usd"
                ], $parsed['host']),
            ]);
            $array = [];
            $html = $response->getBody()->getContents();
            $dom = new Dom();
            $dom->loadStr($html);

            try {
                foreach ($dom->find('a.tc-item') as $element) {
                    $item = [];
                    foreach ($element->find('div') as $key => $element2) {
                        $item[] = $this->funPayGetTextFromItem($element2);
                    }
                    $array[] = $item;
                }
            } catch (ErrorException $exception) {
                dd($element->innerHtml ?? 'Foreach problem', $exception->getMessage());
            }
            $name = $dom->find('div h1')[0]->innerHtml;
            $xlsx = SimpleXLSXGen::fromArray($array);

            return response()->streamDownload(function () use ($xlsx) {
                echo (string)$xlsx;
            }, $name . ' - FunPay - ' . date("Y-m-d H-i-s") .'.xls');
        }
    }

    public function render()
    {
        return view('livewire.parser');
    }

    private function funPayGetTextFromItem($element)
    {
        return match ($element->getAttribute('class')) {
            'tc-user' => trim($element->find('div.media-user-name')[0]->innerHtml),
            default => trim($element->text(true)),
        };
    }
}
