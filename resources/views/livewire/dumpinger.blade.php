<div class="container main">
    <form>
        <div class="input-group mb-3">
            <input wire:model="cookie" type="text" class="form-control" placeholder="G2G Cookie" aria-label="G2G Cookie" aria-describedby="button-addon2">
            <button wire:click="start" wire:loading.attr="disabled" class="btn btn-outline-secondary" type="button" id="button-addon2">Start</button>
        </div>
    </form>


    <div wire:loading wire:target="start" class="container">

        <div class="text-center" >
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

    </div>

    @if(count($games))
        @foreach($games as $gameIndex => $game)
            <h2>{{ $game['name'] }}</h2>
            <form wire:poll.5000ms="foo({{ $gameIndex }})" wire:key="game-{{ $gameIndex }}">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover table-sm">
                        <thead>
                        <tr>
                            <th scope="col">Region</th>
                            <th scope="col">Server</th>
                            <th scope="col">Min stock</th>
                            <th scope="col">Max stock</th>
                            <th scope="col">Competitor Current lowest price</th>
                            <th scope="col">Our price</th>
                            <th scope="col">dump %</th>
                            <th scope="col">Our new price</th>
                        </tr>
                        </thead>
                        <tbody>
                            @foreach($game['items'] as $itemIndex => $item)
                                <tr wire:key="item-{{ $itemIndex }}">
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-region">{{ $item['region'] ?? '' }}</td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-server"><a href="{{ $item['link'] ?? '#' }}" target="_blank">{{ $item['name'] ?? '' }}</a></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-min_stock"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.min_stock" class="form-control" type="text"></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-max_stock"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.max_stock" class="form-control" type="text"></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-competitor_current_lowest_price"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.competitor_current_lowest_price" class="form-control" type="text" disabled></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-our_price"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.our_price" class="form-control" type="text" disabled></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-dump"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.dump" class="form-control" type="text"></td>
                                    <td wire:key="item-{{ $game['name'] }}-{{ $itemIndex }}-our_new_price"><input wire:loading.attr="disabled" wire:model.debounce.500ms="games.{{ $gameIndex }}.items.{{ $itemIndex }}.our_new_price" class="form-control" type="text" disabled></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        @endforeach
    @endif
</div>
<style>
    div.container.main {
        margin-top: 50px;
    }
</style>
