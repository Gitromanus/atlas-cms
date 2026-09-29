@php
    $images = collect();
    try {
        $images = $product->images->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
    } catch (\Throwable) {}
    $imgUrl = $images->first()?->url;
    $imgUrl2 = $images->count() > 1 ? $images->get(1)?->url : null;
    $price = null;
    try { $price = $product->price; } catch (\Throwable) {}
    $stock = null;
    try {
        if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
            $stock = (float) $product->variants->sum('quantity');
        } elseif ($product->relationLoaded('stocks')) {
            $stock = (float) $product->stocks->sum('quantity');
        } else {
            $stock = $product->stockTotal();
        }
    } catch (\Throwable) { $stock = null; }
@endphp
<article class="product-card group">
    <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}" class="product-card__media">
        @if ($imgUrl)
            <img src="{{ $imgUrl }}" alt="{{ $product->name }}" loading="lazy" class="product-card__img product-card__img--primary">
            @if ($imgUrl2)
                <img src="{{ $imgUrl2 }}" alt="" loading="lazy" aria-hidden="true" class="product-card__img product-card__img--secondary">
            @endif
        @else
            <div class="product-card__placeholder">🛍️</div>
        @endif
        @if ($stock !== null)
            @if ($stock > 0)
                <span class="product-card__badge product-card__badge--ok">В наличии</span>
            @else
                <span class="product-card__badge product-card__badge--out">Нет в наличии</span>
            @endif
        @endif
    </a>
    <div class="product-card__body">
        <h3 class="product-card__title">
            <a href="{{ route('product.show', ['productSlug' => $product->slug ?: $product->id]) }}">{{ $product->name }}</a>
        </h3>
        @if ($product->sku)
            <p class="product-card__sku">Арт. {{ $product->sku }}</p>
        @endif
        <div class="product-card__footer">
            @if ($price !== null)
                <p class="product-card__price">{{ number_format((float) $price, 0, '.', ' ') }} ₽</p>
            @else
                <p class="product-card__price product-card__price--empty">Цена по запросу</p>
            @endif
            <form action="{{ route('cart.add') }}" method="POST" class="product-card__cart" onclick="event.stopPropagation()">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" @disabled($stock !== null && $stock <= 0) class="product-card__btn">В корзину</button>
            </form>
        </div>
    </div>
</article>
