<?php

namespace App\Http\Livewire;

use App\Models\DumpGames;
use App\Models\WhiteList;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\StrictException;

class Dumpinger extends Component
{
    public string $cookie = '';
    public bool $checkAll = false;
    public array $games = [];
    public array $whiteLists = [];

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
            'cookies' => CookieJar::fromArray(loadG2GCookie(), '.www.g2g.com'),
        ];
    }

    /**
     * @return void
     */
    public function mount()
    {
//        Log::channel('telegram')->error('kekis');
        foreach (WhiteList::all() as $whiteList) {
            $this->whiteLists[$whiteList->id] = [
                'id' => $whiteList->id,
                'username' => $whiteList->username,
            ];
        }
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
    }

    public function render()
    {
        return view('livewire.dumpinger');
    }

    public function updated($name, $value)
    {
        $path = explode('.', $name);
        dump($path);
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
        } elseif ($path[0] == 'whiteLists') {
            $whiteList = WhiteList::find($path[1]);
            $whiteList->{$path[2]} = $value;
            $whiteList->save();
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

    public function addToWhiteList()
    {
        $whiteList = WhiteList::create();
        $this->whiteLists[$whiteList->id] = '';
    }

    public function removeFromWhiteList($id)
    {
        $whiteList = WhiteList::find($id);
        $whiteList->delete();
        unset($this->whiteLists[$id]);
    }
}
