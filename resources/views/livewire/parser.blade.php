<div class="container">
    <div class="input-group input-group-top mb-3">
        <input wire:model="url" type="text" class="form-control" placeholder="Url" aria-label="Recipient's username"
               aria-describedby="button-addon2">
        <button class="btn btn-outline-secondary" type="button" id="button-addon2" wire:click="parse"
                wire:loading.attr="disabled">
            <span wire:loading class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            <span role="status">Parse</span>
        </button>
    </div>
</div>
<style>
    body {
        background-color: black;
    }

    input {
        background-color: white;
    }

    button {
        color: white !important;
        border-color: white !important;
    }

    .input-group-top {
        margin-top: 30px;
    }
</style>
