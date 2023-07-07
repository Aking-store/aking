<div>

    @foreach ($names as $name)
        <div>
            <button wire:click="csv('{{$name}}')">{{$name}}</button>
        </div>
    @endforeach

    <input wire:model="url" type="text">
    <button wire:click="parse" wire:loading.attr="disabled">Parse</button>
</div>
<style>
    body {
        background-color: black;
    }
    input {
        background-color: white;
    }
</style>
