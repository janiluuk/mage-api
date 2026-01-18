@props(['items' => []])

@if(!empty($items))
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol class="breadcrumbs-list">
        @foreach($items as $index => $item)
            <li class="breadcrumb-item">
                @if($index < count($items) - 1)
                    <a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] }}</a>
                    <span class="breadcrumb-separator">/</span>
                @else
                    <span class="breadcrumb-current">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif

