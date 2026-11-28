{{--
    Tombol halaman (‹ 1 … 4 5 6 … 25 ›) untuk komponen Livewire.
    Pakai: @include('partials.pager', ['page' => $page, 'lastPage' => $lastPage, 'action' => 'goToPage', 'label' => 'Halaman daftar donasi'])
    Komponen menyediakan method `action(int $page)`. Tidak tampil bila hanya satu halaman.
--}}
@if ($lastPage > 1)
    @php
        $pages = collect([1, $lastPage, $page - 1, $page, $page + 1])
            ->filter(fn ($p) => $p >= 1 && $p <= $lastPage)->unique()->sort()->values();
    @endphp
    <nav class="qb-pager" aria-label="{{ $label }}">
        <button type="button" class="qb-page" wire:click="{{ $action }}({{ $page - 1 }})" @disabled($page <= 1) aria-label="Halaman sebelumnya"><span class="qb-flip">@include('partials.icon', ['name' => 'arrow-right', 'size' => 14])</span></button>
        @foreach ($pages as $i => $p)
            @if ($i > 0 && $p - $pages[$i - 1] > 1)<span class="qb-page-gap" aria-hidden="true">…</span>@endif
            <button type="button" @class(['qb-page', 'is-active' => $p === $page]) wire:click="{{ $action }}({{ $p }})" aria-label="Halaman {{ $p }}" @if ($p === $page) aria-current="page" @endif>{{ $p }}</button>
        @endforeach
        <button type="button" class="qb-page" wire:click="{{ $action }}({{ $page + 1 }})" @disabled($page >= $lastPage) aria-label="Halaman berikutnya">@include('partials.icon', ['name' => 'arrow-right', 'size' => 14])</button>
    </nav>
@endif
