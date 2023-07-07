<?php

namespace App\Console\Commands;

use App\Iteration;
use App\Product;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Date;

class ParseG2G extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:parse-g2g';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (Iteration::where('status', 'new')->count() > 0) {
            die();
        }
        $client = new Client([
            'timeout' => 5,
            'connect_timeout' => 5,
            'read_timeout' => 5,
        ]);
        $response = $client->request('GET', 'https://assets.g2g.com/offer/keyword.json');

        $games = json_decode($response->getBody()->getContents(), true);

        $response = $client->request('GET', 'https://assets.g2g.com/offer/categories.json');
        $categories = json_decode($response->getBody()->getContents(), true);
        $categories = array_filter($categories, fn($n) => count($n) == 4);
        $iteration = new Iteration();
        $iteration->save();

        foreach ($categories as $key => $category) {
            try {
                for ($page = 1; $page <= 1000; $page++) {
                    $response = $client->request('GET', 'https://sls.g2g.com/offer/search?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id'] . '&sort=recommended&page_size=100&currency=USD&country=UA&page=' . $page);
                    Log::info('https://sls.g2g.com/offer/search?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id'] . '&sort=recommended&page_size=100&currency=USD&country=UA&page=' . $page);

                    $categoryInfo = json_decode($response->getBody()->getContents(), true);

                    $response = $client->request('GET', 'https://sls.g2g.com/offer/keyword_relation/collection?service_id=' . $category['service_id'] . '&brand_id=' . $category['brand_id']);
                    $dirtAttributes = json_decode($response->getBody()->getContents(), true);
                    $attributes = [];
                    foreach ($dirtAttributes['payload']['results'] as $attribute) {
                        $attributes[$attribute['collection_id']] = $attribute['label']['en'];
                    }

                    foreach ($categoryInfo['payload']['results'] as $categoryProduct) {
//                        dd($categoryProduct);
                        $product = Product::where('offer_id',$categoryProduct['offer_id'])->where('site_name','g2g')->first();
                        if ($product) {
                            $prices = $product->prices;
                            $prices[$iteration->id] = $categoryProduct['display_price'];
                            $product->prices = $prices;
                        } else {
                            $product = new Product();
                            $product->site_name = 'g2g';
                            $product->offer_id = $categoryProduct['offer_id'];
                            $product->seller_outer_id = $categoryProduct['seller_id'];
                            $product->seller_outer_name = $categoryProduct['username'];
                            $product->game_outer_id = $category['brand_id'];
                            $product->game_outer_name = trim($category['marketing_title']['en']);
                            $product->category_outer_id = $category['service_id'];
                            $product->category_outer_name = trim($key);

                            $product->prices = [$iteration->id => $categoryProduct['display_price']];
                        }

                        $product->iteration = $iteration->id;
                        $product->title = trim($categoryProduct['title']);
                        $product->price = $categoryProduct['display_price'];
                        $product->score = $categoryProduct['score'];
                        $product->updated = date("Y-m-d H:i:s", $categoryProduct['updated_at']/1000);

                        foreach ($categoryProduct['offer_attributes'] as $attribute) {
                            $product->{$attributes[$attribute['collection_id']]} = $attribute['value'];
                        }

//                        dd($product);
                        $product->save();

                    }
//                    die();
//                    dd($products);
                }
            } catch (\Exception $exception) {
                Log::error($exception);
//                dd($exception);
            }
            $iteration->touch();
            unset($attributes);
        }
        $iteration->status = 'ready';
        $iteration->save();
    }
}
