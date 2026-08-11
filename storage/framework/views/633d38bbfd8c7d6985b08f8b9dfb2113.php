<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', $website->locale ?: 'en')); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo e($title); ?> | <?php echo e($website->name); ?></title>
    <meta name="description" content="<?php echo e($description); ?>">
    <?php if(in_array($viewMode, ['cart','checkout','account-login','account-register','account','order-lookup','order-detail','order-success'], true)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <link rel="canonical" href="<?php echo e(request()->url()); ?>">
    <meta property="og:title" content="<?php echo e($title); ?> | <?php echo e($website->name); ?>">
    <meta property="og:description" content="<?php echo e($description); ?>">
    <?php if($viewMode === 'product' && filled($product->featured_image_url)): ?>
        <meta property="og:image" content="<?php echo e($product->featured_image_url); ?>">
    <?php endif; ?>
    <?php if($useSiteShell ?? false): ?>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>.cosmic-commerce-site-shell{font-family:Manrope,ui-sans-serif,system-ui,sans-serif}</style>
    <?php endif; ?>
    <style>
        :root{--c-primary:<?php echo e($palette['primary']); ?>;--c-accent:<?php echo e($palette['accent']); ?>;--c-surface:<?php echo e($palette['surface']); ?>;--c-text:<?php echo e($palette['text']); ?>;--c-muted:<?php echo e($palette['muted'] ?? '#64748B'); ?>;--c-border:<?php echo e($palette['border'] ?? '#E2E8F0'); ?>;--c-bg:<?php echo e($palette['background'] ?? '#FFFFFF'); ?>;--shadow:0 18px 55px rgba(15,23,42,.09)}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#fff;color:#111827;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.55}a{color:inherit;text-decoration:none}img{max-width:100%;display:block}.wrap{width:min(1180px,calc(100% - 40px));margin:auto}.topbar{background:var(--c-primary);color:var(--c-text);position:sticky;top:0;z-index:20;box-shadow:0 1px 0 rgba(255,255,255,.12)}.nav{min-height:72px;display:flex;align-items:center;gap:24px;justify-content:space-between}.brand{font-size:20px;font-weight:850;letter-spacing:-.03em}.navlinks{display:flex;gap:18px;align-items:center;font-size:14px;font-weight:650}.navlinks a{opacity:.86}.navlinks a:hover{opacity:1}.hero{background:linear-gradient(135deg,var(--c-primary),var(--c-surface));color:var(--c-text);padding:72px 0 62px}.hero.category-hero{padding:56px 0}.hero h1{font-size:clamp(38px,6vw,68px);line-height:1.02;letter-spacing:-.055em;margin:0 0 16px}.hero p{font-size:18px;max-width:700px;margin:0;opacity:.82}.breadcrumb{padding:20px 0;font-size:13px;color:#64748b}.breadcrumb a{font-weight:700}.breadcrumb span{margin:0 8px;color:#cbd5e1}.catalog{padding:28px 0 84px}.catalog-layout{display:grid;grid-template-columns:220px 1fr;gap:34px}.filter-card{position:sticky;top:92px;border:1px solid #e5e7eb;border-radius:20px;padding:18px;height:max-content}.filter-title{font-weight:800;margin-bottom:12px}.cat-link{display:flex;justify-content:space-between;gap:10px;padding:9px 0;color:#475569;font-size:14px}.cat-link.active{color:var(--c-primary);font-weight:800}.toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap}.search{display:flex;gap:8px;flex:1;min-width:260px}.input,.select{border:1px solid #dbe2ea;background:#fff;border-radius:12px;min-height:44px;padding:0 13px;font:inherit;color:#0f172a}.input{flex:1}.btn{border:0;background:var(--c-primary);color:var(--c-text);border-radius:12px;min-height:44px;padding:0 17px;font-weight:800;cursor:pointer}.btn.ghost{background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.product-card{border:1px solid #e7ebf0;border-radius:22px;overflow:hidden;background:#fff;transition:.2s ease;position:relative}.product-card:hover{transform:translateY(-3px);box-shadow:var(--shadow)}.card-media{aspect-ratio:1/1;background:#f4f6f8;overflow:hidden}.card-media img{width:100%;height:100%;object-fit:cover}.placeholder{width:100%;height:100%;display:grid;place-items:center;color:#94a3b8;font-weight:800;background:linear-gradient(135deg,#f8fafc,#eef2f7)}.card-body{padding:17px}.badge{position:absolute;top:12px;left:12px;background:var(--c-primary);color:var(--c-text);padding:7px 10px;border-radius:999px;font-size:11px;font-weight:850;letter-spacing:.04em;text-transform:uppercase}.badge.sale{background:var(--c-accent)}.card-title{font-size:17px;font-weight:850;letter-spacing:-.02em;margin:0 0 7px}.price{display:flex;gap:8px;align-items:baseline;font-weight:850}.old{font-size:13px;color:#94a3b8;text-decoration:line-through}.muted{color:#64748b}.empty{border:1px dashed #cbd5e1;border-radius:22px;padding:64px 24px;text-align:center;color:#64748b}.pagination{display:flex;gap:8px;justify-content:center;margin-top:36px}.pagination a,.pagination span{min-width:40px;height:40px;display:grid;place-items:center;border:1px solid #e2e8f0;border-radius:10px;font-weight:700}.pagination .current{background:var(--c-primary);color:var(--c-text);border-color:var(--c-primary)}.product-shell{padding:18px 0 96px;background:linear-gradient(180deg,#fff 0%,#fbfcfe 100%)}.product-main{display:grid;grid-template-columns:minmax(0,1.04fr) minmax(360px,.96fr);gap:64px;align-items:start}.gallery-main{aspect-ratio:1/1;background:#f4f6f8;border-radius:30px;overflow:hidden;border:1px solid #e8edf3;box-shadow:0 24px 60px rgba(15,23,42,.08)}.gallery-main img{width:100%;height:100%;object-fit:cover}.thumbs{display:grid;grid-template-columns:repeat(5,76px);gap:10px;margin-top:14px}.thumb{aspect-ratio:1/1;border:1px solid #dbe3ec;border-radius:13px;overflow:hidden;background:#f8fafc;transition:.2s ease}.thumb:hover{transform:translateY(-2px);border-color:#94a3b8}.thumb img{width:100%;height:100%;object-fit:cover}.product-info{padding:28px 0 0;position:sticky;top:28px}.eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:800;color:var(--c-primary);margin-bottom:10px}.product-info h1{font-size:clamp(36px,4.2vw,52px);line-height:1.08;letter-spacing:-.045em;font-weight:700;margin:0 0 16px;max-width:760px}.product-price{font-size:30px;line-height:1.15;font-weight:800;letter-spacing:-.025em;margin:0 0 22px}.lead{font-size:16px;line-height:1.75;color:#526071;max-width:640px;margin:0 0 8px}.stock{display:inline-flex;align-items:center;gap:8px;margin:16px 0;padding:8px 11px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:13px;font-weight:800}.stock.out{background:#fff1f2;color:#be123c}.options{margin-top:22px}.option{margin-bottom:18px}.option-label{font-weight:800;margin-bottom:8px}.option-values{display:flex;gap:8px;flex-wrap:wrap}.option-value{border:1px solid #dbe2ea;border-radius:11px;padding:9px 12px;background:#fff;font-size:13px;font-weight:700;cursor:pointer}.option-value.selected{border-color:var(--c-primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--c-primary) 18%,transparent)}.option-value:disabled{opacity:.38;cursor:not-allowed;background:#f8fafc}.cart-warning{margin:0 0 18px;padding:14px 16px;border-radius:14px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:14px}.cart-warning strong{display:block;margin-bottom:3px}.low-stock{color:#b45309;font-size:13px;font-weight:800;margin-top:6px}.swatch{width:16px;height:16px;border-radius:50%;display:inline-block;vertical-align:-3px;margin-right:6px;border:1px solid rgba(0,0,0,.12)}.notice{margin-top:22px;padding:16px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:14px}.description{margin-top:58px;border:1px solid #e7ebf0;border-radius:24px;padding:30px 32px;background:#fff;box-shadow:0 12px 36px rgba(15,23,42,.04)}.description h2,.related h2{font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-.03em;margin:0 0 14px}.description>div{color:#526071;line-height:1.8;font-size:15px}.related{padding:50px 0 90px;background:#f8fafc}.flash{margin:0 auto 18px;padding:13px 16px;border-radius:14px;background:#ecfdf5;color:#047857;font-weight:750;border:1px solid #a7f3d0}.flash.error{background:#fff1f2;color:#9f1239;border-color:#fecdd3}.field-error{font-size:12px;color:#be123c;font-weight:700}.btn[disabled]{opacity:.58;cursor:not-allowed}.cart-link{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;background:rgba(255,255,255,.12)}.cart-shell{padding:24px 0 88px}.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:30px}.cart-panel,.summary-card,.checkout-card{border:1px solid #e5e7eb;border-radius:22px;background:#fff;padding:22px}.cart-line{display:grid;grid-template-columns:90px 1fr auto;gap:16px;align-items:center;padding:18px 0;border-bottom:1px solid #eef2f7}.cart-line:last-child{border-bottom:0}.cart-image{width:90px;height:90px;border-radius:14px;overflow:hidden;background:#f4f6f8}.cart-image img{width:100%;height:100%;object-fit:cover}.cart-title{font-weight:850}.cart-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}.qty{width:78px}.summary-card{height:max-content;position:sticky;top:92px}.summary-row{display:flex;justify-content:space-between;gap:20px;padding:10px 0}.summary-total{font-size:20px;font-weight:900;border-top:1px solid #e5e7eb;margin-top:10px;padding-top:18px}.purchase-form{display:grid;grid-template-columns:minmax(88px,120px) minmax(180px,1fr);gap:12px;align-items:center;margin-top:24px;max-width:520px}.purchase-form .qty{width:100%;min-height:50px}.purchase-form .btn{min-height:50px;border-radius:14px;font-size:14px;box-shadow:0 10px 24px color-mix(in srgb,var(--c-primary) 22%,transparent)}.checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:30px}.checkout-card h2,.summary-card h2{margin-top:0}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:13px;font-weight:800}.field input,.field select{width:100%;border:1px solid #dbe2ea;border-radius:12px;min-height:46px;padding:0 13px;font:inherit}.account-wrap{max-width:920px}.account-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}.order-card{display:grid;grid-template-columns:1fr auto;gap:18px;padding:18px 0;border-bottom:1px solid #eef2f7}.order-card:last-child{border-bottom:0}.status-pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:12px;font-weight:850;text-transform:capitalize}.auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px}.auth-card{border:1px solid #e5e7eb;border-radius:22px;background:#fff;padding:26px}.detail-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px}.footer{background:#0f172a;color:#cbd5e1;padding:34px 0}.footer-inner{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;font-size:14px}
        @media(max-width:900px){.catalog-layout,.cart-layout,.checkout-grid,.auth-grid,.detail-grid{grid-template-columns:1fr}.summary-card{position:static}.filter-card{position:static}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.product-main{grid-template-columns:1fr;gap:34px}.product-info{position:static;padding-top:4px}.navlinks{display:none}}@media(max-width:580px){.wrap{width:min(100% - 24px,1180px)}.hero{padding:50px 0}.grid{grid-template-columns:1fr}.toolbar,.search{align-items:stretch}.select{width:100%}.product-shell{padding-top:12px}.product-info h1{font-size:36px}.thumbs{grid-template-columns:repeat(4,minmax(0,1fr))}.purchase-form{grid-template-columns:96px 1fr}}
    </style>
</head>
<body>
<?php if($useSiteShell ?? false): ?>
<div class="cosmic-commerce-site-shell"><?php echo $siteHeaderHtml; ?></div>
<?php else: ?>
<header class="topbar"><div class="wrap nav"><a class="brand" href="<?php echo e($shopUrl); ?>"><?php echo e($website->name); ?></a><nav class="navlinks"><a href="<?php echo e($shopUrl); ?>">Shop</a><?php $__currentLoopData = $categories->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $navCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($categoryUrl($navCategory)); ?>"><?php echo e($navCategory->name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($accountUrl); ?>">Account</a><a class="cart-link" href="<?php echo e($cartUrl); ?>">Cart <strong><?php echo e($cartCount); ?></strong></a></nav></div></header>
<?php endif; ?>
<main>
    <div class="wrap breadcrumb"><?php $__currentLoopData = $breadcrumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $crumb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(!$loop->first): ?><span>›</span><?php endif; ?> <?php if($crumb['url']): ?><a href="<?php echo e($crumb['url']); ?>"><?php echo e($crumb['label']); ?></a><?php else: ?><?php echo e($crumb['label']); ?><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <?php if(session('commerce_success')): ?><div class="wrap flash"><?php echo e(session('commerce_success')); ?></div><?php endif; ?>
    <?php if($errors->any()): ?><div class="wrap flash" style="background:#fff1f2;color:#be123c;border-color:#fecdd3"><?php echo e($errors->first()); ?></div><?php endif; ?>

<?php
$storefrontPartial = match ($viewMode) {
    'shop', 'category' => 'commerce.storefront-modes.catalog',
    'product' => 'commerce.storefront-modes.product',
    'cart' => 'commerce.storefront-modes.cart',
    'checkout' => 'commerce.storefront-modes.checkout',
    'account-login' => 'commerce.storefront-modes.account-login',
    'account-register' => 'commerce.storefront-modes.account-register',
    'account' => 'commerce.storefront-modes.account',
    'order-lookup' => 'commerce.storefront-modes.order-lookup',
    'order-detail' => 'commerce.storefront-modes.order-detail',
    'order-success' => 'commerce.storefront-modes.order-success',
    default => 'commerce.storefront-modes.catalog',
};
?>
<?php echo $__env->make($storefrontPartial, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</main>
<?php if($useSiteShell ?? false): ?>
<div class="cosmic-commerce-site-shell"><?php echo $siteFooterHtml; ?></div>
<?php else: ?>
<footer class="footer"><div class="wrap footer-inner"><strong><?php echo e($website->name); ?></strong><span>Powered by Cosmic Commerce</span></div></footer>
<?php endif; ?>
<script>
    (function () {
        var storageKey = 'cosmic-commerce.checkout.<?php echo e($website->id); ?>';
        var detailsForm = document.querySelector('[data-commerce-checkout-details-form]');
        var destinationForm = document.querySelector('form[action="<?php echo e($checkoutUrl); ?>"][method="get"]');
        if (!detailsForm || !destinationForm || !window.localStorage) return;

        var remember = detailsForm.querySelector('[data-commerce-remember-details]');
        var forget = detailsForm.querySelector('[data-commerce-forget-details]');
        var status = detailsForm.querySelector('[data-commerce-remember-status]');
        var allowed = ['first_name', 'last_name', 'email', 'address1', 'city', 'postal_code'];

        function readSaved() {
            try {
                var raw = window.localStorage.getItem(storageKey);
                if (!raw) return null;
                var parsed = JSON.parse(raw);
                return parsed && typeof parsed === 'object' ? parsed : null;
            } catch (error) {
                return null;
            }
        }

        function safeValue(value, max) {
            return String(value || '').trim().slice(0, max || 255);
        }

        function collect() {
            var data = {};
            allowed.forEach(function (name) {
                var input = detailsForm.elements[name];
                if (input) data[name] = safeValue(input.value, name === 'email' ? 254 : 255);
            });
            var country = destinationForm.elements.country;
            var region = destinationForm.elements.region;
            data.country = country ? safeValue(country.value, 2).toUpperCase() : '';
            data.region = region ? safeValue(region.value, 120) : '';
            data.saved_at = new Date().toISOString();
            return data;
        }

        function fillBlankFields(saved) {
            if (!saved) return;
            allowed.forEach(function (name) {
                var input = detailsForm.elements[name];
                if (input && !input.value && saved[name]) input.value = String(saved[name]);
            });
        }

        function updateUi(hasSaved, message) {
            if (remember) remember.checked = !!hasSaved;
            if (forget) forget.style.display = hasSaved ? 'inline-flex' : 'none';
            if (status) status.textContent = message || (hasSaved ? 'Saved details are available on this device.' : '');
        }

        var saved = readSaved();
        fillBlankFields(saved);
        updateUi(!!saved);

        if (saved) {
            var countryInput = destinationForm.elements.country;
            var regionInput = destinationForm.elements.region;
            var currentCountry = countryInput ? safeValue(countryInput.value, 2).toUpperCase() : '';
            var currentRegion = regionInput ? safeValue(regionInput.value, 120) : '';
            var savedCountry = safeValue(saved.country, 2).toUpperCase();
            var savedRegion = safeValue(saved.region, 120);

            if (!currentCountry && savedCountry && countryInput && countryInput.querySelector('option[value="' + savedCountry.replace(/"/g, '') + '"]')) {
                var url = new URL(window.location.href);
                url.searchParams.set('country', savedCountry);
                if (savedRegion) url.searchParams.set('region', savedRegion);
                window.location.replace(url.toString());
                return;
            }

            if (currentCountry && currentCountry === savedCountry && !currentRegion && savedRegion) {
                var regionUrl = new URL(window.location.href);
                regionUrl.searchParams.set('region', savedRegion);
                window.location.replace(regionUrl.toString());
                return;
            }
        }

        detailsForm.addEventListener('submit', function () {
            if (!remember || !remember.checked) {
                try { window.localStorage.removeItem(storageKey); } catch (error) {}
                updateUi(false);
                return;
            }
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(collect()));
                updateUi(true, 'Details saved on this device.');
            } catch (error) {
                if (status) status.textContent = 'This browser could not save your details.';
            }
        });

        if (forget) {
            forget.addEventListener('click', function () {
                try { window.localStorage.removeItem(storageKey); } catch (error) {}
                updateUi(false, 'Saved checkout details removed from this device.');
            });
        }
    })();

    document.querySelectorAll('[data-commerce-payment-form]').forEach(function (form) {
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.dataset.originalLabel = button.textContent;
            button.dataset.originalDisabled = button.disabled ? '1' : '0';
        }
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) return;
            if (!button || button.disabled || form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Opening PayPal…';
        });
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-commerce-payment-form]').forEach(function (form) {
            form.dataset.submitting = '0';
            var button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.removeAttribute('aria-busy');
            if (button.dataset.originalLabel) button.textContent = button.dataset.originalLabel;
            button.disabled = button.dataset.originalDisabled === '1';
        });
    });
</script>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\my-custom-cms\resources\views/commerce/storefront.blade.php ENDPATH**/ ?>