import { useState } from 'react';
import { getEffectiveTheme } from '../../../../theme/Theme';
import { colorFamilies } from '../../../../theme/colorFamilies';
import { RepeatableControls, RepeatableRemoveButton } from "../Shared/RepeatableControls";

const schema = (type, title, purpose, defaults = {}) => ({
    type,
    title,
    category: 'Commerce',
    purpose,
    description: purpose,
    tags: ['commerce', 'shop', 'product'],
    defaults: { theme: 'auto', ...defaults },
});

export const CommerceProductGridSchema = schema('commerce_product_grid', 'Product Grid', 'Show live store products in a premium responsive catalog.', { heading: 'Featured products', text: 'Discover customer favorites and new arrivals.', limit: 8, featured_only: false });
export const CommerceCatalogGridSchema = schema('commerce_catalog_grid', 'Shop Catalog Grid', 'A classic storefront catalog with search, category filters, sorting and balanced product cards.', { heading: 'Shop the catalog', text: 'Browse products, filter collections, and find what fits.', limit: 12, show_toolbar: true });
export const CommerceCatalogEditorialSchema = schema('commerce_catalog_editorial', 'Shop Catalog Editorial', 'A spacious image-led catalog for premium, fashion, lifestyle and curated stores.', { heading: 'Curated for you', text: 'Explore an image-first collection with a more editorial rhythm.', limit: 10, show_toolbar: true });
export const CommerceCatalogCompactSchema = schema('commerce_catalog_compact', 'Shop Catalog Compact', 'A denser catalog layout for stores with larger inventories and fast scanning.', { heading: 'Browse all products', text: 'A compact catalog built for quick comparison.', limit: 16, show_toolbar: true });
export const CommerceCategoriesSchema = schema('commerce_categories', 'Shop Categories', 'Show live product categories as storefront navigation.', { heading: 'Shop by category', text: 'Find the collection that fits what you need.' });
export const CommerceFeaturedProductsSchema = schema('commerce_featured_products', 'Featured Products', 'Highlight featured catalog products in a premium merchandising row.', { heading: 'Featured picks', text: 'A curated selection worth a closer look.', limit: 4 });
export const CommerceFeaturedCollectionSchema = schema('commerce_featured_collection', 'Featured Collection', 'Show products from one selected category as a focused collection.', { heading: 'Featured collection', text: 'Explore a focused edit from one collection.', category_id: null, limit: 4, button_label: 'View collection' });
export const CommercePromoSplitSchema = schema('commerce_promo_split', 'Promo Split Banner', 'Promote a collection or seasonal offer with image, message and CTA.', { eyebrow: 'Limited collection', heading: 'A standout offer for the season', text: 'Pair a strong message with a product-led visual and a clear next step.', button_label: 'Shop the collection', button_url: '/shop', image_url: '/storage/cms-images/background/background-3.avif', image_alt: 'Featured collection' });
export const CommerceBenefitsStripSchema = schema('commerce_benefits_strip', 'Commerce Benefits Strip', 'Build trust after the catalog with shipping, checkout, returns and support benefits.', { heading: 'Shop with confidence', benefit_1_title: 'Secure checkout', benefit_1_text: 'Protected payment flow', benefit_2_title: 'Fast delivery', benefit_2_text: 'Clear shipping options', benefit_3_title: 'Easy returns', benefit_3_text: 'Straightforward support', benefit_4_title: 'Here to help', benefit_4_text: 'Customer care when needed' });

export const CommerceProductGallerySchema = schema('commerce_product_gallery', 'Product Gallery', 'Show the selected product image gallery.', { heading: 'Product gallery', product_id: null });
export const CommercePriceSchema = schema('commerce_price', 'Product Price', 'Show live product pricing and sale state.', { product_id: null, label: 'Price' });
export const CommerceVariationSelectorSchema = schema('commerce_variation_selector', 'Variation Selector', 'Show live Size, Color, Material and other product options.', { product_id: null, heading: 'Choose your options' });
export const CommerceRelatedProductsSchema = schema('commerce_related_products', 'Related Products', 'Show products related to the selected catalog item.', { product_id: null, heading: 'You may also like', limit: 4 });
export const CommerceMiniCartSchema = schema('commerce_mini_cart', 'Mini Cart Shell', 'Add a premium cart summary shell ready for checkout integration.', { heading: 'Your cart', empty_text: 'Your cart is ready for products.', button_label: 'View cart' });
export const CommerceCartClassicSchema = schema('commerce_cart_classic', 'Cart Classic', 'A balanced cart layout with product lines and an order summary card.', { heading: 'Your cart', text: 'Review your items before checkout.', checkout_label: 'Proceed to checkout', continue_label: 'Continue shopping' });
export const CommerceCartSplitSchema = schema('commerce_cart_split', 'Cart Split Summary', 'A premium two-column cart with a stronger sticky-style summary panel.', { heading: 'Review your bag', text: 'Everything looks good? Continue securely to checkout.', checkout_label: 'Secure checkout', continue_label: 'Keep shopping' });
export const CommerceCartCompactSchema = schema('commerce_cart_compact', 'Cart Compact', 'A dense cart layout for fast scanning and larger orders.', { heading: 'Cart summary', text: 'Quickly review quantities and totals.', checkout_label: 'Checkout', continue_label: 'Back to shop' });
export const CommerceCheckoutClassicSchema = schema('commerce_checkout_classic', 'Checkout Classic', 'A clear single-flow checkout preview with customer, delivery and payment sections.', { heading: 'Checkout', text: 'Complete your details and review the order securely.', payment_label: 'Continue to payment', help_text: 'Secure checkout powered by the commerce runtime.' });
export const CommerceCheckoutSplitSchema = schema('commerce_checkout_split', 'Checkout Split', 'A premium two-column checkout with customer details beside a stronger order summary.', { heading: 'Secure checkout', text: 'Delivery details on the left, live order summary on the right.', payment_label: 'Pay securely', help_text: 'Shipping, tax, coupons and payment stay runtime-controlled.' });
export const CommerceCheckoutExpressSchema = schema('commerce_checkout_express', 'Checkout Express', 'A compact clean checkout preview for fast mobile-first purchase flows.', { heading: 'Express checkout', text: 'A streamlined path from customer details to payment.', payment_label: 'Complete purchase', help_text: 'Fast, focused and secure.' });

function useCommerce(block, globalTheme, commerce) {
    // Builder can keep a stale resolvedTheme after the user explicitly switches
    // the Spark palette (e.g. PRIMARY -> WHITE). Explicit theme selections must
    // win; resolvedTheme is only the computed value for AUTO. This mirrors the
    // HTML compiler, which resolves explicit primary/white/surface directly.
    const requestedTheme = block?.theme && block.theme !== 'auto'
        ? block.theme
        : (block?.resolvedTheme || 'auto');
    // getEffectiveTheme returns the resolved color-family object (not a family key).
    // Treating that object as a colorFamilies key forced every Commerce Spark to Midnight.
    const resolvedFamily = getEffectiveTheme(requestedTheme, globalTheme);
    const family = resolvedFamily?.palette ? resolvedFamily : (colorFamilies[resolvedFamily] || colorFamilies.midnight);
    const palette = family?.palette || {};
    const colors = {
        background: palette.background || '#243447',
        surface: palette.surface || palette.background || '#30475E',
        text: palette.text || '#F8FAFC',
        muted: palette.muted || '#CBD5E1',
        accent: palette.accent || '#60A5FA',
        border: palette.border || 'rgba(148,163,184,.28)',
    };
    const allProducts = Array.isArray(commerce?.products) ? commerce.products : [];
    const products = allProducts.filter((product) => product?.status === 'published' && product?.visibility !== 'hidden');
    const categories = Array.isArray(commerce?.categories) ? commerce.categories : [];
    const countries = Array.isArray(commerce?.countries) ? commerce.countries : [];
    const currency = commerce?.currency || 'USD';
    const decimals = Number(commerce?.currency_decimals ?? 2);
    const scale = 10 ** Math.max(0, decimals);
    const money = (minor) => minor == null || minor === ''
        ? '—'
        : new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(minor) / scale);
    const product = products.find((p) => Number(p.id) === Number(block?.product_id)) || products[0] || null;
    return { family, colors, products, categories, countries, currency, decimals, money, product };
}


const commerceControlScheme = (colors) => {
    const text = String(colors?.text || '').toLowerCase();
    const dark = ['#f8fafc', '#ffffff', '#fff', 'white'].includes(text);
    return {
        colorScheme: dark ? 'dark' : 'light',
        optionStyle: dark
            ? { backgroundColor: colors?.surface || colors?.background || '#0f172a', color: colors?.text || '#f8fafc' }
            : { backgroundColor: colors?.surface || colors?.background || '#ffffff', color: colors?.text || '#0f172a' },
    };
};

const Section = ({ colors, children }) => (
    <section className="w-full px-6 py-16 md:px-12 lg:py-20" style={{ background: colors.background, color: colors.text }}>
        <div className="mx-auto max-w-7xl">{children}</div>
    </section>
);

const Head = ({ block }) => (
    <div className="mb-8 max-w-2xl">
        <h2 className="text-3xl font-semibold tracking-[-0.035em] md:text-4xl">{block.heading}</h2>
        {block.text ? <p className="mt-3 max-w-xl text-[15px] leading-7 opacity-70">{block.text}</p> : null}
    </div>
);

const ProductBindingBar = ({ block, commerce, onUpdate, builderMode }) => {
    if (!builderMode) return null;
    const products = (commerce?.products || []).filter((product) => product?.status === 'published' && product?.visibility !== 'hidden');
    return (
        <div className="mb-4 flex flex-col gap-2 rounded-xl border border-dashed border-violet-400/40 bg-violet-500/5 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-violet-500">Commerce data binding</p>
                <p className="text-xs opacity-65">Choose the live product this Spark represents in Builder and Preview.</p>
            </div>
            <select
                value={block?.product_id || products[0]?.id || ''}
                onChange={(event) => onUpdate?.({ product_id: Number(event.target.value) || null })}
                className="w-full max-w-full rounded-lg border border-current/15 bg-transparent px-3 py-2 text-sm font-semibold sm:w-auto sm:min-w-[220px]"
            >
                {products.length ? products.map((product) => <option key={product.id} value={product.id} className="text-slate-900">{product.title}</option>) : <option value="">No published products</option>}
            </select>
        </div>
    );
};

const imageUrl = (product) => product?.featured_image_url
    || product?.gallery?.[0]?.url
    || product?.images?.[0]?.url
    || product?.images?.[0]?.image_url
    || '/storage/cms-images/background/background-1.avif';

const productUrl = (product) => product?.storefront_url || (product?.slug ? `/product/${product.slug}` : '/shop');
const categoryUrl = (category) => category?.storefront_url || (category?.slug ? `/shop/category/${category.slug}` : '/shop');
const productPriceMinor = (product) => product?.sale_price_minor ?? product?.regular_price_minor ?? null;

function CommerceCatalogToolbar({ categories, search, setSearch, category, setCategory, sort, setSort, colors }) {
    const controls = commerceControlScheme(colors);
    const selectStyle = { borderColor: colors.border, color: colors.text, background: colors.surface, colorScheme: controls.colorScheme };
    return (
        <div className="mb-7 grid gap-3 rounded-2xl border p-3 sm:grid-cols-[1fr_auto_auto]" style={{ borderColor: colors.border, background: colors.surface }}>
            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search products" className="min-h-11 rounded-xl border bg-transparent px-4 text-sm outline-none" style={{ borderColor: colors.border, color: colors.text }} />
            <select value={category} onChange={(event) => setCategory(event.target.value)} className="min-h-11 rounded-xl border px-3 text-sm font-semibold" style={selectStyle}>
                <option value="" style={controls.optionStyle}>All categories</option>
                {categories.map((item) => <option key={item.id} value={String(item.id)} style={controls.optionStyle}>{item.name}</option>)}
            </select>
            <select value={sort} onChange={(event) => setSort(event.target.value)} className="min-h-11 rounded-xl border px-3 text-sm font-semibold" style={selectStyle}>
                <option value="featured" style={controls.optionStyle}>Featured</option>
                <option value="newest" style={controls.optionStyle}>Newest</option>
                <option value="price_asc" style={controls.optionStyle}>Price: low to high</option>
                <option value="price_desc" style={controls.optionStyle}>Price: high to low</option>
                <option value="name" style={controls.optionStyle}>Name</option>
            </select>
        </div>
    );
}

function CommerceCatalogBlock({ block, globalTheme, commerce, variant = 'grid' }) {
    const c = useCommerce(block, globalTheme, commerce);
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('');
    const [sort, setSort] = useState('featured');
    const limit = Math.max(1, Number(block.limit || (variant === 'compact' ? 16 : 12)));
    const categoryMap = new Map(c.categories.map((item) => [Number(item.id), item.name]));
    let products = c.products.filter((product) => {
        const matchesSearch = !search.trim() || String(product.title || '').toLowerCase().includes(search.trim().toLowerCase());
        const matchesCategory = !category || (product.category_ids || []).map(Number).includes(Number(category));
        return matchesSearch && matchesCategory;
    });
    products = [...products].sort((a, b) => {
        if (sort === 'price_asc') return Number(productPriceMinor(a) ?? 0) - Number(productPriceMinor(b) ?? 0);
        if (sort === 'price_desc') return Number(productPriceMinor(b) ?? 0) - Number(productPriceMinor(a) ?? 0);
        if (sort === 'name') return String(a.title || '').localeCompare(String(b.title || ''));
        if (sort === 'newest') return Number(b.id || 0) - Number(a.id || 0);
        return Number(Boolean(b.is_featured)) - Number(Boolean(a.is_featured));
    }).slice(0, limit);

    const toolbar = block.show_toolbar === false ? null : <CommerceCatalogToolbar categories={c.categories} search={search} setSearch={setSearch} category={category} setCategory={setCategory} sort={sort} setSort={setSort} colors={c.colors} />;
    return (
        <Section colors={c.colors}>
            <Head block={block} />
            {toolbar}
            {products.length ? (
                variant === 'editorial' ? (
                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-12">
                        {products.map((product, index) => (
                            <a key={product.id} href={productUrl(product)} className={`group overflow-hidden rounded-[26px] border ${index % 5 === 0 ? 'lg:col-span-7' : 'lg:col-span-5'}`} style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                                <img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className={`w-full object-cover transition duration-500 group-hover:scale-[1.02] ${index % 5 === 0 ? 'aspect-[16/10]' : 'aspect-[4/3]'}`} />
                                <div className="p-6 sm:p-7">
                                    <p className="text-[10px] font-bold uppercase tracking-[0.18em] opacity-55">{(product.category_ids || []).map((id) => categoryMap.get(Number(id))).filter(Boolean)[0] || 'Collection'}</p>
                                    <div className="mt-2 flex items-end justify-between gap-5"><h3 className="text-xl font-semibold tracking-[-0.03em]">{product.title}</h3><p className="shrink-0 text-base font-bold">{c.money(productPriceMinor(product))}</p></div>
                                </div>
                            </a>
                        ))}
                    </div>
                ) : variant === 'compact' ? (
                    <div className="divide-y rounded-2xl border" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                        {products.map((product) => (
                            <a key={product.id} href={productUrl(product)} className="grid grid-cols-[72px_1fr_auto] items-center gap-4 p-3.5 transition hover:bg-black/5" style={{ borderColor: c.colors.border }}>
                                <img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className="h-[72px] w-[72px] rounded-xl object-cover" />
                                <div className="min-w-0"><h3 className="truncate text-sm font-semibold">{product.title}</h3><p className="mt-1 truncate text-xs opacity-60">{(product.category_ids || []).map((id) => categoryMap.get(Number(id))).filter(Boolean).join(' · ') || 'Catalog product'}</p></div>
                                <div className="text-right"><p className="text-sm font-bold">{c.money(productPriceMinor(product))}</p>{product.track_inventory ? <p className="mt-1 text-[11px] opacity-60">{Number(product.stock_quantity || 0) > 0 ? `${product.stock_quantity} in stock` : (product.allow_backorders ? 'Backorder' : 'Out of stock')}</p> : null}</div>
                            </a>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {products.map((product) => (
                            <a key={product.id} href={productUrl(product)} className="group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style={{ borderColor: c.colors.border, background: c.colors.surface, boxShadow: '0 12px 35px rgba(2,6,23,.10)' }}>
                                <img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-[1.025]" />
                                <div className="p-5"><h3 className="text-[16px] font-semibold tracking-[-0.02em]">{product.title}</h3><div className="mt-3 flex items-center justify-between gap-3"><p className="text-[15px] font-bold">{c.money(productPriceMinor(product))}</p>{product.is_featured ? <span className="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide" style={{ background: `${c.colors.accent}1F`, color: c.colors.accent }}>Featured</span> : null}</div></div>
                            </a>
                        ))}
                    </div>
                )
            ) : <Empty text="No products match this catalog view yet." />}
        </Section>
    );
}

export function CommerceCatalogGridBlock(props) { return <CommerceCatalogBlock {...props} variant="grid" />; }
export function CommerceCatalogEditorialBlock(props) { return <CommerceCatalogBlock {...props} variant="editorial" />; }
export function CommerceCatalogCompactBlock(props) { return <CommerceCatalogBlock {...props} variant="compact" />; }


const ProductCard = ({ product, c }) => (
    <a href={productUrl(product)} className="group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style={{ borderColor: c.colors.border, background: c.colors.surface, boxShadow: '0 12px 35px rgba(2,6,23,.10)' }}>
        <img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className="aspect-[4/5] w-full object-cover transition duration-500 group-hover:scale-[1.025]" />
        <div className="p-5"><h3 className="text-[16px] font-semibold tracking-[-0.02em]">{product.title}</h3><p className="mt-2 text-[15px] font-bold">{c.money(productPriceMinor(product))}</p></div>
    </a>
);

export function CommerceFeaturedProductsBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const featured = c.products.filter((product) => product.is_featured);
    const products = (featured.length ? featured : c.products).slice(0, Number(block.limit || 4));
    return <Section colors={c.colors}><Head block={block} />{products.length ? <div data-commerce-body className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{products.map((product) => <ProductCard key={product.id} product={product} c={c} />)}</div> : <Empty text="Mark products as featured to populate this Spark." />}</Section>;
}

const CategoryBindingBar = ({ block, commerce, onUpdate, builderMode }) => {
    if (!builderMode) return null;
    const categories = commerce?.categories || [];
    return <div className="mb-4 flex flex-col gap-2 rounded-xl border border-dashed border-violet-400/40 bg-violet-500/5 p-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-[10px] font-bold uppercase tracking-[0.16em] text-violet-500">Collection binding</p><p className="text-xs opacity-65">Choose which live category this Spark merchandises.</p></div><select value={block?.category_id || categories[0]?.id || ''} onChange={(event)=>onUpdate?.({category_id:Number(event.target.value)||null})} className="w-full max-w-full rounded-lg border border-current/15 bg-transparent px-3 py-2 text-sm font-semibold sm:w-auto sm:min-w-[220px]">{categories.length ? categories.map((category)=><option key={category.id} value={category.id} className="text-slate-900">{category.name}</option>) : <option value="">No categories</option>}</select></div>;
};

export function CommerceFeaturedCollectionBlock({ block, globalTheme, commerce, onUpdate, builderMode = false }) {
    const c = useCommerce(block, globalTheme, commerce);
    const category = c.categories.find((item)=>Number(item.id)===Number(block.category_id)) || c.categories[0] || null;
    const products = category ? c.products.filter((product)=>(product.category_ids || []).map(Number).includes(Number(category.id))).slice(0, Number(block.limit || 4)) : [];
    return <Section colors={c.colors}><CategoryBindingBar block={block} commerce={commerce} onUpdate={onUpdate} builderMode={builderMode} /><div className="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><Head block={{...block, heading:block.heading || category?.name || 'Featured collection'}} />{category ? <a href={categoryUrl(category)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-bold" style={{borderColor:c.colors.border, background:c.colors.surface}}>{block.button_label || 'View collection'}</a> : null}</div>{products.length ? <div data-commerce-body className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{products.map((product)=><ProductCard key={product.id} product={product} c={c} />)}</div> : <Empty text="Choose a category with published products to populate this collection." />}</Section>;
}

export function CommercePromoSplitBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    return <Section colors={c.colors}><div className="grid overflow-hidden rounded-[30px] border lg:grid-cols-[1.02fr_.98fr]" style={{borderColor:c.colors.border, background:c.colors.surface}}><div className="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><p className="text-[11px] font-bold uppercase tracking-[0.2em]" style={{color:c.colors.accent}}>{block.eyebrow || 'Limited collection'}</p><h2 className="mt-4 text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">{block.heading}</h2><p className="mt-4 max-w-xl text-[15px] leading-7" style={{color:c.colors.muted}}>{block.text}</p><div><a href={block.button_url || '/shop'} className="mt-7 inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-2.5 text-sm font-bold" style={{background:c.colors.accent, color:c.colors.background}}>{block.button_label || 'Shop the collection'}</a></div></div><img src={block.image_url || '/storage/cms-images/background/background-3.avif'} alt={block.image_alt || block.heading || ''} className="h-full min-h-[300px] w-full object-cover" /></div></Section>;
}

export function CommerceBenefitsStripBlock({ block, onUpdate, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const benefitCount = Math.max(1, Math.min(4, Number(block.benefit_count) || 4));
    const items = [1, 2, 3, 4].slice(0, benefitCount).map((slot) => ({
        slot,
        title: block[`benefit_${slot}_title`],
        text: block[`benefit_${slot}_text`],
    }));
    const removeBenefit = (index) => {
        if (benefitCount <= 1) return;
        const patch = { benefit_count: benefitCount - 1 };
        for (let slot = index + 1; slot < benefitCount; slot += 1) {
            patch[`benefit_${slot}_title`] = block[`benefit_${slot + 1}_title`] || '';
            patch[`benefit_${slot}_text`] = block[`benefit_${slot + 1}_text`] || '';
        }
        patch[`benefit_${benefitCount}_title`] = '';
        patch[`benefit_${benefitCount}_text`] = '';
        onUpdate?.(patch);
    };
    return <Section colors={c.colors}><div><div className="rounded-[26px] border px-6 py-7 sm:px-8" style={{borderColor:c.colors.border, background:c.colors.surface}}>{block.heading ? <h2 className="mb-6 text-xl font-semibold tracking-[-0.03em]">{block.heading}</h2> : null}<div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">{items.map((item,index)=><div key={item.slot} className="group relative flex gap-3 pr-9"><RepeatableRemoveButton overlay onRemove={() => removeBenefit(index)} disabled={benefitCount <= 1} label="Remove benefit"/><div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-black" style={{background:`${c.colors.accent}1F`, color:c.colors.accent}}>✓</div><div><h3 className="text-sm font-bold">{item.title}</h3><p className="mt-1 text-xs leading-5" style={{color:c.colors.muted}}>{item.text}</p></div></div>)}</div></div><RepeatableControls onAdd={() => benefitCount < 4 && onUpdate?.({ benefit_count: benefitCount + 1 })} onRemove={() => {}} canAdd={benefitCount < 4} canRemove={false} addLabel="Add benefit" showRemove={false}/></div></Section>;
}

export function CommerceProductGridBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const products = c.products
        .filter((product) => !block.featured_only || product.is_featured)
        .slice(0, Number(block.limit || 8));

    return (
        <Section colors={c.colors}>
            <Head block={block} />
            {products.length ? (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {products.map((product) => (
                        <a key={product.id} href={productUrl(product)} className="group overflow-hidden rounded-[22px] border transition duration-300 hover:-translate-y-1 hover:shadow-2xl" style={{ borderColor: c.colors.border, background: c.colors.surface, boxShadow: '0 12px 35px rgba(2,6,23,.10)' }}>
                            <div className="overflow-hidden bg-black/5"><img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className="aspect-square w-full object-cover transition duration-500 group-hover:scale-[1.025]" /></div>
                            <div className="p-5">
                                <h3 className="text-[16px] font-semibold tracking-[-0.02em]">{product.title}</h3>
                                <div className="mt-3 flex items-center justify-between gap-3">
                                    <p className="text-[15px] font-bold">{c.money(productPriceMinor(product))}</p>
                                    {product.track_inventory ? <span className="rounded-full px-2.5 py-1 text-[11px] font-semibold" style={{ background: `${c.colors.accent}1F`, color: c.colors.accent }}>{Number(product.stock_quantity || 0) > 0 ? `${product.stock_quantity} in stock` : (product.allow_backorders ? 'Backorder' : 'Out of stock')}</span> : null}
                                </div>
                            </div>
                        </a>
                    ))}
                </div>
            ) : <Empty text="Publish catalog-visible products in Commerce → Products and they will appear here automatically." />}
        </Section>
    );
}

export function CommerceCategoriesBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    return (
        <Section colors={c.colors}>
            <Head block={block} />
            {c.categories.length ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {c.categories.map((category) => (
                        <a key={category.id} href={categoryUrl(category)} className="rounded-2xl border p-6" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                            <div className="text-lg font-bold">{category.name}</div>
                            <p className="mt-2 text-sm opacity-65">{category.description || 'Explore this collection'}</p>
                        </a>
                    ))}
                </div>
            ) : <Empty text="Create product categories to populate this Spark." />}
        </Section>
    );
}

export function CommerceProductGalleryBlock({ block, globalTheme, commerce, onUpdate, builderMode = false }) {
    const c = useCommerce(block, globalTheme, commerce);
    const images = [
        imageUrl(c.product),
        ...(c.product?.gallery || []).map((image) => image.url || image.image_url),
        ...(c.product?.images || []).map((image) => image.url || image.image_url),
    ].filter((value, index, list) => value && list.indexOf(value) === index);

    return (
        <Section colors={c.colors}>
            <ProductBindingBar block={block} commerce={commerce} onUpdate={onUpdate} builderMode={builderMode} />
            <Head block={{ ...block, heading: block.heading || c.product?.title || 'Product gallery' }} />
            {c.product ? (
                <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
                    <img src={images[0]} alt={c.product.featured_image_alt || c.product.title || ''} className="aspect-square w-full rounded-2xl object-cover" />
                    <div className="grid grid-cols-2 gap-3">
                        {images.slice(1, 5).map((url, index) => <img key={`${url}-${index}`} src={url} alt="" className="aspect-square w-full rounded-xl object-cover" />)}
                    </div>
                </div>
            ) : <Empty text="Create and publish a product to connect this gallery." />}
        </Section>
    );
}

export function CommercePriceBlock({ block, globalTheme, commerce, onUpdate, builderMode = false }) {
    const c = useCommerce(block, globalTheme, commerce);
    const product = c.product;
    return (
        <Section colors={c.colors}>
            <ProductBindingBar block={block} commerce={commerce} onUpdate={onUpdate} builderMode={builderMode} />
            <div className="rounded-2xl border p-6" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                <p className="text-xs font-semibold uppercase tracking-widest opacity-60">{block.label}</p>
                {product ? (
                    <div className="mt-2 flex items-end gap-3">
                        <span className="text-4xl font-black">{c.money(productPriceMinor(product))}</span>
                        {product.sale_price_minor != null ? <span className="pb-1 text-lg line-through opacity-45">{c.money(product.regular_price_minor)}</span> : null}
                    </div>
                ) : <Empty text="Create and publish a product to show live pricing." />}
            </div>
        </Section>
    );
}

export function CommerceVariationSelectorBlock({ block, globalTheme, commerce, onUpdate, builderMode = false }) {
    const c = useCommerce(block, globalTheme, commerce);
    const [selected, setSelected] = useState({});
    const options = c.product?.options || [];

    return (
        <Section colors={c.colors}>
            <ProductBindingBar block={block} commerce={commerce} onUpdate={onUpdate} builderMode={builderMode} />
            <Head block={block} />
            {options.length ? (
                <div className="space-y-5">
                    {options.map((option) => (
                        <div key={option.id}>
                            <p className="mb-2 text-sm font-semibold">{option.name}</p>
                            <div className="flex flex-wrap gap-2">
                                {(option.values || []).map((value) => (
                                    <button
                                        key={value.id}
                                        type="button"
                                        onClick={() => setSelected((current) => ({ ...current, [option.id]: value.id }))}
                                        className="rounded-xl border px-4 py-2 text-sm font-semibold"
                                        style={{
                                            borderColor: selected[option.id] === value.id ? c.colors.accent : c.colors.border,
                                            background: selected[option.id] === value.id ? c.colors.accent : 'transparent',
                                            color: selected[option.id] === value.id ? 'white' : c.colors.text,
                                        }}
                                    >
                                        {value.swatch_hex ? <span className="mr-2 inline-block h-3 w-3 rounded-full align-middle" style={{ background: value.swatch_hex }} /> : null}
                                        {value.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            ) : <Empty text="This product has no variations yet. Add options in Commerce → Products → Variations." />}
        </Section>
    );
}

export function CommerceRelatedProductsBlock({ block, globalTheme, commerce, onUpdate, builderMode = false }) {
    const c = useCommerce(block, globalTheme, commerce);
    const product = c.product;
    const categoryIds = new Set((product?.category_ids || product?.categories?.map((category) => category.id) || []).map(Number));
    const related = c.products
        .filter((candidate) => candidate.id !== product?.id && (!categoryIds.size || (candidate.category_ids || []).some((id) => categoryIds.has(Number(id)))))
        .slice(0, Number(block.limit || 4));

    return (
        <Section colors={c.colors}>
            <ProductBindingBar block={block} commerce={commerce} onUpdate={onUpdate} builderMode={builderMode} />
            <Head block={block} />
            {related.length ? (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {related.map((candidate) => (
                        <a key={candidate.id} href={productUrl(candidate)} className="overflow-hidden rounded-2xl border" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                            <img src={imageUrl(candidate)} alt={candidate.featured_image_alt || candidate.title || ''} className="aspect-square w-full object-cover" />
                            <div className="p-4">
                                <b>{candidate.title}</b>
                                <p className="mt-1 text-sm">{c.money(productPriceMinor(candidate))}</p>
                            </div>
                        </a>
                    ))}
                </div>
            ) : <Empty text="Related products will appear automatically from matching categories." />}
        </Section>
    );
}

function CartPreviewLines({ c, compact = false }) {
    const products = c.products.slice(0, compact ? 3 : 2);
    if (!products.length) return <Empty text="Live cart items will appear here on the protected Cart route." />;
    return (
        <div className={compact ? 'divide-y rounded-2xl border' : 'space-y-3'} style={compact ? { borderColor: c.colors.border } : undefined}>
            {products.map((product, index) => (
                <div key={product.id} className={compact ? 'grid grid-cols-[64px_1fr_auto] items-center gap-3 p-3' : 'grid grid-cols-[82px_1fr_auto] items-center gap-4 rounded-2xl border p-3.5'} style={!compact ? { borderColor: c.colors.border, background: c.colors.surface } : undefined}>
                    <img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className={compact ? 'h-16 w-16 rounded-xl object-cover' : 'h-[82px] w-[82px] rounded-xl object-cover'} />
                    <div className="min-w-0"><h3 className="truncate text-sm font-bold">{product.title}</h3><p className="mt-1 text-xs" style={{ color: c.colors.muted }}>Qty {index + 1} · Demo preview</p></div>
                    <strong className="text-sm">{c.money(productPriceMinor(product) == null ? null : productPriceMinor(product) * (index + 1))}</strong>
                </div>
            ))}
        </div>
    );
}

function CartSummaryCard({ block, c, checkoutUrl, emphasized = false }) {
    const previewSubtotal = c.products.slice(0, 2).reduce((sum, product, index) => sum + Number(productPriceMinor(product) || 0) * (index + 1), 0);
    return <aside className={`rounded-[26px] border p-6 ${emphasized ? 'lg:sticky lg:top-6' : ''}`} style={{ borderColor: c.colors.border, background: c.colors.surface }}>
        <p className="text-[11px] font-bold uppercase tracking-[0.18em]" style={{ color: c.colors.muted }}>Order summary</p>
        <div className="mt-5 flex items-center justify-between text-sm"><span style={{ color: c.colors.muted }}>Preview subtotal</span><strong>{c.money(previewSubtotal)}</strong></div>
        <div className="mt-3 flex items-center justify-between text-sm"><span style={{ color: c.colors.muted }}>Shipping & tax</span><span>At checkout</span></div>
        <div className="my-5 border-t" style={{ borderColor: c.colors.border }} />
        <div className="flex items-center justify-between"><strong>Estimated total</strong><strong className="text-xl">{c.money(previewSubtotal)}</strong></div>
        <a href={checkoutUrl} className="mt-6 block w-full rounded-xl px-4 py-3 text-center text-sm font-bold" style={{ background: c.colors.accent, color: c.colors.background }}>{block.checkout_label || 'Proceed to checkout'}</a>
        <p className="mt-3 text-center text-[11px] leading-5" style={{ color: c.colors.muted }}>Builder preview only. Live quantities, coupons and totals remain controlled by the commerce runtime.</p>
    </aside>;
}

export function CommerceCartClassicBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const checkoutUrl = commerce?.runtime_urls?.checkout || '/checkout';
    const shopUrl = commerce?.runtime_urls?.shop || '/shop';
    return <Section colors={c.colors}><Head block={block} /><div className="grid gap-7 lg:grid-cols-[1fr_360px]"><div><CartPreviewLines c={c} /><a href={shopUrl} className="mt-5 inline-flex text-sm font-bold" style={{ color: c.colors.accent }}>← {block.continue_label || 'Continue shopping'}</a></div><CartSummaryCard block={block} c={c} checkoutUrl={checkoutUrl} /></div></Section>;
}

export function CommerceCartSplitBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const checkoutUrl = commerce?.runtime_urls?.checkout || '/checkout';
    const shopUrl = commerce?.runtime_urls?.shop || '/shop';
    return <Section colors={c.colors}><div className="grid gap-8 lg:grid-cols-[1.2fr_.8fr] lg:items-start"><div><Head block={block} /><CartPreviewLines c={c} /><a href={shopUrl} className="mt-5 inline-flex text-sm font-bold" style={{ color: c.colors.accent }}>← {block.continue_label || 'Keep shopping'}</a></div><CartSummaryCard block={block} c={c} checkoutUrl={checkoutUrl} emphasized /></div></Section>;
}

export function CommerceCartCompactBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const checkoutUrl = commerce?.runtime_urls?.checkout || '/checkout';
    const shopUrl = commerce?.runtime_urls?.shop || '/shop';
    return <Section colors={c.colors}><div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><Head block={block} /><a href={shopUrl} className="shrink-0 text-sm font-bold" style={{ color: c.colors.accent }}>← {block.continue_label || 'Back to shop'}</a></div><div className="grid gap-5 lg:grid-cols-[1fr_320px]"><CartPreviewLines c={c} compact /><CartSummaryCard block={block} c={c} checkoutUrl={checkoutUrl} /></div></Section>;
}


function CheckoutField({ label, placeholder, wide = false, c }) {
    return <label className={wide ? 'sm:col-span-2' : ''}>
        <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.13em]" style={{ color: c.colors.muted }}>{label}</span>
        <div className="min-h-11 rounded-xl border px-3.5 py-3 text-sm" style={{ borderColor: c.colors.border, background: c.colors.surface, color: c.colors.muted }}>{placeholder}</div>
    </label>;
}

function CheckoutCustomerPanel({ c, compact = false }) {
    const controls = commerceControlScheme(c.colors);
    const fallbackCountries = [
        { code: 'PH', name: 'Philippines' }, { code: 'US', name: 'United States' },
        { code: 'AU', name: 'Australia' }, { code: 'CA', name: 'Canada' },
        { code: 'GB', name: 'United Kingdom' }, { code: 'SG', name: 'Singapore' },
        { code: 'JP', name: 'Japan' }, { code: 'NZ', name: 'New Zealand' },
    ];
    const countries = c.countries?.length ? c.countries : fallbackCountries;
    const [country, setCountry] = useState('');
    return <div className={`rounded-[26px] border ${compact ? 'p-4 sm:p-5' : 'p-5 sm:p-6'}`} style={{ borderColor: c.colors.border, background: c.colors.surface }}>
        <div className="flex items-center justify-between gap-4"><h3 className="text-base font-bold">Customer details</h3><span className="text-[10px] font-bold uppercase tracking-[0.14em]" style={{ color: c.colors.accent }}>Runtime bound</span></div>
        <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <CheckoutField label="First name" placeholder="Alex" c={c} />
            <CheckoutField label="Last name" placeholder="Morgan" c={c} />
            <CheckoutField label="Email" placeholder="alex@example.com" wide c={c} />
            <label>
                <span className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.13em]" style={{ color: c.colors.muted }}>Country</span>
                <select value={country} onChange={(event) => setCountry(event.target.value)} className="min-h-11 w-full rounded-xl border px-3.5 text-sm outline-none" style={{ borderColor: c.colors.border, background: c.colors.surface, color: c.colors.text, colorScheme: controls.colorScheme }}>
                    <option value="" style={controls.optionStyle}>Select country / region</option>
                    {countries.map((item) => <option key={item.code} value={item.code} style={controls.optionStyle}>{item.name}</option>)}
                </select>
            </label>
            <CheckoutField label="Region" placeholder={country ? 'State / province / region' : 'Choose country first'} c={c} />
            {!compact ? <CheckoutField label="Street address" placeholder="123 Commerce Street" wide c={c} /> : null}
        </div>
        <p className="mt-3 text-[11px] leading-5" style={{ color: c.colors.muted }}>{country ? `Preview destination: ${countries.find((item) => item.code === country)?.name || country}. Live checkout recalculates shipping and tax.` : 'Choose a country to preview the destination control. Live checkout remains server-calculated.'}</p>
    </div>;
}

function CheckoutDeliveryPanel({ c }) {
    return <div className="rounded-[26px] border p-5 sm:p-6" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
        <h3 className="text-base font-bold">Delivery & promo</h3>
        <div className="mt-5 grid gap-3 sm:grid-cols-2"><CheckoutField label="Shipping" placeholder="Calculated by destination" c={c} /><CheckoutField label="Promo code" placeholder="Enter code" c={c} /></div>
        <p className="mt-4 text-xs leading-5" style={{ color: c.colors.muted }}>Live checkout recalculates shipping, coupons and tax on the protected runtime route.</p>
    </div>;
}

function CheckoutOrderSummary({ block, c, emphasized = false }) {
    const items = c.products.slice(0, 2);
    const subtotal = items.reduce((sum, product, index) => sum + Number(productPriceMinor(product) || 0) * (index + 1), 0);
    return <aside className={`rounded-[26px] border p-5 sm:p-6 ${emphasized ? 'lg:sticky lg:top-6' : ''}`} style={{ borderColor: c.colors.border, background: c.colors.surface }}>
        <div className="flex items-center justify-between"><h3 className="text-base font-bold">Order summary</h3><span className="text-[10px] font-bold uppercase tracking-[0.14em]" style={{ color: c.colors.accent }}>Preview</span></div>
        <div className="mt-5 space-y-3">{items.length ? items.map((product, index) => <div key={product.id} className="flex items-center gap-3"><img src={imageUrl(product)} alt={product.featured_image_alt || product.title || ''} className="h-12 w-12 rounded-xl object-cover" /><div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold">{product.title}</p><p className="text-[11px]" style={{ color: c.colors.muted }}>Qty {index + 1}</p></div><strong className="text-xs">{c.money(Number(productPriceMinor(product) || 0) * (index + 1))}</strong></div>) : <p className="text-sm" style={{ color: c.colors.muted }}>Live order items appear at checkout.</p>}</div>
        <div className="my-5 border-t" style={{ borderColor: c.colors.border }} />
        <div className="flex items-center justify-between text-sm"><span style={{ color: c.colors.muted }}>Preview subtotal</span><strong>{c.money(subtotal)}</strong></div>
        <div className="mt-3 flex items-center justify-between text-sm"><span style={{ color: c.colors.muted }}>Shipping & tax</span><span>Calculated live</span></div>
        <div className="my-5 border-t" style={{ borderColor: c.colors.border }} />
        <div className="flex items-center justify-between"><strong>Total</strong><strong className="text-xl">{c.money(subtotal)}</strong></div>
        <div className="mt-6 rounded-xl px-4 py-3 text-center text-sm font-bold" style={{ background: c.colors.accent, color: c.colors.background }}>{block.payment_label || 'Continue to payment'}</div>
        <p className="mt-3 text-center text-[11px] leading-5" style={{ color: c.colors.muted }}>{block.help_text || 'Secure checkout powered by the commerce runtime.'}</p>
    </aside>;
}

export function CommerceCheckoutClassicBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    return <Section colors={c.colors}><Head block={block} /><div className="grid gap-6 lg:grid-cols-[1fr_360px]"><div className="space-y-5"><CheckoutCustomerPanel c={c} /><CheckoutDeliveryPanel c={c} /></div><CheckoutOrderSummary block={block} c={c} /></div></Section>;
}

export function CommerceCheckoutSplitBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    return <Section colors={c.colors}><div className="grid gap-8 lg:grid-cols-[1.12fr_.88fr] lg:items-start"><div><Head block={block} /><div className="space-y-5"><CheckoutCustomerPanel c={c} /><CheckoutDeliveryPanel c={c} /></div></div><CheckoutOrderSummary block={block} c={c} emphasized /></div></Section>;
}

export function CommerceCheckoutExpressBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    return <Section colors={c.colors}><div className="mx-auto max-w-5xl"><div className="text-center"><div className="mx-auto max-w-2xl"><Head block={block} /></div></div><div className="mt-7 grid gap-5 lg:grid-cols-[1fr_330px]"><CheckoutCustomerPanel c={c} compact /><CheckoutOrderSummary block={block} c={c} /></div></div></Section>;
}

export function CommerceMiniCartBlock({ block, globalTheme, commerce }) {
    const c = useCommerce(block, globalTheme, commerce);
    const cartUrl = commerce?.runtime_urls?.cart || '/cart';
    return (
        <Section colors={c.colors}>
            <div className="ml-auto max-w-md rounded-3xl border p-6 shadow-xl" style={{ borderColor: c.colors.border, background: c.colors.surface }}>
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-bold">{block.heading}</h2>
                    <span className="rounded-full px-2.5 py-1 text-xs font-bold" style={{ background: c.colors.accent, color: 'white' }}>Live cart</span>
                </div>
                <p className="py-10 text-center text-sm opacity-60">{block.empty_text}</p>
                <a href={cartUrl} className="mt-4 block w-full rounded-xl px-4 py-3 text-center font-bold" style={{ background: c.colors.accent, color: 'white' }}>{block.button_label}</a>
            </div>
        </Section>
    );
}

function Empty({ text }) {
    return <div className="rounded-2xl border border-dashed border-current/20 p-8 text-center text-sm opacity-60">{text}</div>;
}