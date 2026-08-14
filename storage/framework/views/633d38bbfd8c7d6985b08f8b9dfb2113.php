<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', $website->locale ?: 'en')); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($title); ?> | <?php echo e($website->name); ?></title>
    <meta name="description" content="<?php echo e($description); ?>">
    <?php if(in_array($viewMode, ['cart','checkout','account-login','account-register','account','order-lookup','order-detail','order-success'], true)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <link rel="canonical" href="<?php echo e(request()->url()); ?>">
    <meta property="og:title" content="<?php echo e($title); ?> | <?php echo e($website->name); ?>">
    <meta property="og:description" content="<?php echo e($description); ?>">
    <?php if($viewMode === 'product' && filled($product->featured_image_url)): ?>
        <meta property="og:image" content="<?php echo e($assetUrl($product->featured_image_url)); ?>">
    <?php endif; ?>
    <?php if($useSiteShell ?? false): ?>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>.cosmic-commerce-site-shell{font-family:Manrope,ui-sans-serif,system-ui,sans-serif}</style>
    <?php endif; ?>
    <style>
        :root{--c-primary:<?php echo e($palette['primary']); ?>;--c-accent:<?php echo e($palette['accent']); ?>;--c-surface:<?php echo e($palette['surface']); ?>;--c-text:<?php echo e($palette['text']); ?>;--c-muted:<?php echo e($palette['muted'] ?? '#64748B'); ?>;--c-border:<?php echo e($palette['border'] ?? '#E2E8F0'); ?>;--c-bg:<?php echo e($palette['background'] ?? '#FFFFFF'); ?>;--c-page:color-mix(in srgb,var(--c-primary) 4%,#fff);--c-page-strong:color-mix(in srgb,var(--c-primary) 8%,#fff);--c-card:#fff;--c-ink:#0f172a;--c-subtle:#64748b;--shadow:0 18px 55px color-mix(in srgb,var(--c-primary) 11%,transparent)}
        .commerce-global-hero{background:var(--c-primary);color:#fff;padding:clamp(44px,7vw,82px) 0}.commerce-global-hero h1{color:inherit;font-size:clamp(36px,5vw,64px);line-height:1.04;letter-spacing:-.045em;margin:0 0 12px}.commerce-global-hero p{max-width:720px;margin:0;color:inherit;opacity:.82;font-size:17px}.commerce-page-style-clean .commerce-global-hero{background:#fff!important;background-color:#fff!important;background-image:none!important;color:#0f172a!important;border-bottom:1px solid var(--c-border)!important}.commerce-page-style-clean .commerce-global-hero :is(h1,h2,h3,h4,h5,h6){color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}.commerce-page-style-clean .commerce-global-hero p{color:#64748b!important;-webkit-text-fill-color:#64748b!important;opacity:1!important}.commerce-page-style-clean .commerce-global-hero .eyebrow{color:var(--c-primary)!important;-webkit-text-fill-color:var(--c-primary)!important;opacity:1!important}.commerce-overlay-header>.cosmic-commerce-site-shell:first-child{position:absolute;inset:0 0 auto 0;z-index:40}.commerce-overlay-header .commerce-global-hero{padding-top:clamp(118px,12vw,154px)}.commerce-overlay-header main>.breadcrumb{padding-top:22px}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--c-page);color:var(--c-ink);font-family:Manrope,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.55}h1,h2,h3,h4,h5,h6{font-family:Manrope,ui-sans-serif,system-ui,sans-serif;font-weight:700;color:var(--c-ink)}a{color:inherit;text-decoration:none}img{max-width:100%;display:block}.wrap{width:min(1180px,calc(100% - 40px));margin:auto}.topbar{background:var(--c-primary);color:var(--c-text);position:sticky;top:0;z-index:20;box-shadow:0 1px 0 rgba(255,255,255,.12)}.nav{min-height:72px;display:flex;align-items:center;gap:24px;justify-content:space-between}.brand{font-size:20px;font-weight:700;letter-spacing:-.03em}.navlinks{display:flex;gap:18px;align-items:center;font-size:14px;font-weight:650}.navlinks a{opacity:.86}.navlinks a:hover{opacity:1}.hero{background:linear-gradient(135deg,var(--c-primary),var(--c-surface));color:var(--c-text);padding:72px 0 62px}.hero.category-hero{padding:56px 0}.hero h1{font-size:clamp(38px,6vw,68px);line-height:1.02;letter-spacing:-.055em;margin:0 0 16px}.hero p{font-size:18px;max-width:700px;margin:0;opacity:.82}.breadcrumb{padding:22px 0;font-size:13px;color:var(--c-subtle)}.breadcrumb a{font-weight:700}.breadcrumb span{margin:0 8px;color:#cbd5e1}.catalog{padding:34px 0 92px;background:linear-gradient(180deg,var(--c-page) 0%,#fff 100%)}.catalog-layout{display:grid;grid-template-columns:220px 1fr;gap:34px}.filter-card{position:sticky;top:92px;border:1px solid color-mix(in srgb,var(--c-primary) 14%,#e5e7eb);border-radius:22px;padding:20px;height:max-content;background:var(--c-card);box-shadow:0 10px 34px color-mix(in srgb,var(--c-primary) 7%,transparent)}.filter-title{font-weight:800;margin-bottom:12px}.cat-link{display:flex;justify-content:space-between;gap:10px;padding:9px 0;color:#475569;font-size:14px}.cat-link.active{color:var(--c-primary);font-weight:800}.toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap}.search{display:flex;gap:8px;flex:1;min-width:260px}.input,.select{border:1px solid color-mix(in srgb,var(--c-primary) 16%,#dbe2ea);background:#fff;border-radius:12px;min-height:44px;padding:0 13px;font:inherit;color:var(--c-ink)}.input{flex:1}.btn{border:0;background:var(--c-primary);color:var(--c-text);border-radius:13px;min-height:44px;padding:0 18px;font-family:Manrope,ui-sans-serif,system-ui,sans-serif;font-weight:700;cursor:pointer;box-shadow:0 8px 22px color-mix(in srgb,var(--c-primary) 18%,transparent);transition:.18s ease}.btn:hover{transform:translateY(-1px);filter:saturate(1.04)}.commerce-page-style-clean .btn:not(.ghost){background:var(--c-primary)!important;background-color:var(--c-primary)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border-color:var(--c-primary)!important}.commerce-page-style-clean .btn:not(.ghost) :is(span,strong,small){color:#fff!important;-webkit-text-fill-color:#fff!important}.btn.ghost{background:#fff;color:var(--c-ink);border:1px solid color-mix(in srgb,var(--c-primary) 14%,#e2e8f0);box-shadow:none}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.product-card{border:1px solid color-mix(in srgb,var(--c-primary) 12%,#e7ebf0);border-radius:24px;overflow:hidden;background:var(--c-card);transition:.2s ease;position:relative;box-shadow:0 12px 34px color-mix(in srgb,var(--c-primary) 6%,transparent)}.product-card:hover{transform:translateY(-3px);box-shadow:var(--shadow)}.card-media{aspect-ratio:1/1;background:#f4f6f8;overflow:hidden}.card-media img{width:100%;height:100%;object-fit:cover}.placeholder{width:100%;height:100%;display:grid;place-items:center;color:#94a3b8;font-weight:800;background:linear-gradient(135deg,#f8fafc,#eef2f7)}.card-body{padding:17px}.badge{position:absolute;top:12px;left:12px;background:var(--c-primary);color:var(--c-text);padding:7px 10px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.badge.sale{background:var(--c-accent)}.badge.new{background:color-mix(in srgb,var(--c-primary) 76%,#0ea5e9);color:#fff}.badge.out{background:#0f172a;color:#fff}.card-title{font-size:17px;font-weight:700;letter-spacing:-.02em;margin:0 0 7px}.price{display:flex;gap:8px;align-items:baseline;font-weight:700}.old{font-size:13px;color:#94a3b8;text-decoration:line-through}.muted{color:var(--c-subtle)}.empty{border:1px dashed #cbd5e1;border-radius:22px;padding:64px 24px;text-align:center;color:#64748b}.pagination{display:flex;gap:8px;justify-content:center;margin-top:36px}.pagination a,.pagination span{min-width:40px;height:40px;display:grid;place-items:center;border:1px solid #e2e8f0;border-radius:10px;font-weight:700}.pagination .current{background:var(--c-primary);color:var(--c-text);border-color:var(--c-primary)}.product-shell{padding:22px 0 104px;background:linear-gradient(180deg,var(--c-page) 0%,#fff 72%)}.product-main{display:grid;grid-template-columns:minmax(0,1.04fr) minmax(360px,.96fr);gap:64px;align-items:start}.gallery-main{aspect-ratio:1/1;background:#f4f6f8;border-radius:30px;overflow:hidden;border:1px solid color-mix(in srgb,var(--c-primary) 12%,#e8edf3);box-shadow:0 24px 60px color-mix(in srgb,var(--c-primary) 9%,rgba(15,23,42,.08));position:relative}.product-badges{position:absolute;top:16px;left:16px;z-index:2;display:flex;gap:8px;flex-wrap:wrap}.product-badge{display:inline-flex;align-items:center;min-height:30px;padding:0 11px;border-radius:999px;background:var(--c-primary);color:var(--c-text);font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 8px 24px rgba(15,23,42,.12)}.product-badge.sale{background:var(--c-accent)}.product-badge.new{background:color-mix(in srgb,var(--c-primary) 76%,#0ea5e9);color:#fff}.product-badge.out{background:#0f172a;color:#fff}.product-badge.featured{background:color-mix(in srgb,var(--c-primary) 88%,#fff)}.gallery-main img{width:100%;height:100%;object-fit:cover}.thumbs{display:grid;grid-template-columns:repeat(5,76px);gap:10px;margin-top:14px}.thumb{aspect-ratio:1/1;border:1px solid #dbe3ec;border-radius:13px;overflow:hidden;background:#f8fafc;transition:.2s ease}.thumb:hover{transform:translateY(-2px);border-color:#94a3b8}.thumb img{width:100%;height:100%;object-fit:cover}.product-info{padding:28px 0 0;position:sticky;top:28px}.eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:11px;font-weight:800;color:var(--c-primary);margin-bottom:10px}.product-info h1{font-size:clamp(36px,4.2vw,52px);line-height:1.08;letter-spacing:-.045em;font-weight:700;margin:0 0 16px;max-width:760px}.product-price{font-size:30px;line-height:1.15;font-weight:800;letter-spacing:-.025em;margin:0 0 22px}.lead{font-size:16px;line-height:1.75;color:#526071;max-width:640px;margin:0 0 8px}.stock{display:inline-flex;align-items:center;gap:8px;margin:16px 0;padding:8px 11px;border-radius:999px;background:#ecfdf5;color:#047857;font-size:13px;font-weight:800}.stock.out{background:#fff1f2;color:#be123c}.options{margin-top:22px}.option{margin-bottom:18px}.option-label{font-weight:800;margin-bottom:8px}.option-values{display:flex;gap:8px;flex-wrap:wrap}.option-value{border:1px solid color-mix(in srgb,var(--c-primary) 14%,#dbe2ea);border-radius:12px;padding:10px 13px;background:#fff;color:var(--c-ink);font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.16s ease}.option-value:hover:not(:disabled){transform:translateY(-1px);border-color:color-mix(in srgb,var(--c-primary) 42%,#dbe2ea)}.option-value--size{min-width:48px;justify-content:center}.option-value--color{padding:8px 12px 8px 8px}.option-value.selected{border-color:var(--c-primary);box-shadow:0 0 0 2px color-mix(in srgb,var(--c-primary) 18%,transparent)}.option-value:disabled{opacity:.38;cursor:not-allowed;background:#f8fafc}.cart-warning{margin:0 0 18px;padding:14px 16px;border-radius:14px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-size:14px}.cart-warning strong{display:block;margin-bottom:3px}.low-stock{color:#b45309;font-size:13px;font-weight:800;margin-top:6px}.swatch{width:20px;height:20px;border-radius:50%;display:inline-grid;place-items:center;vertical-align:-4px;border:1px solid rgba(0,0,0,.14);box-shadow:inset 0 0 0 2px rgba(255,255,255,.5);font-size:9px;font-style:normal;font-weight:800}.swatch--fallback{background:color-mix(in srgb,var(--c-primary) 10%,#fff);color:var(--c-primary)}.notice{margin-top:22px;padding:16px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:14px}.description{margin-top:58px;border:1px solid color-mix(in srgb,var(--c-primary) 12%,#e7ebf0);border-radius:26px;padding:32px 34px;background:var(--c-card);box-shadow:0 14px 40px color-mix(in srgb,var(--c-primary) 6%,transparent)}.description h2,.related h2{font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-.03em;margin:0 0 14px}.description>div{color:#526071;line-height:1.8;font-size:15px}.related{padding:56px 0 96px;background:var(--c-page-strong)}.flash{margin:0 auto 18px;padding:13px 16px;border-radius:14px;background:#ecfdf5;color:#047857;font-weight:750;border:1px solid #a7f3d0}.flash.error{background:#fff1f2;color:#9f1239;border-color:#fecdd3}.field-error{font-size:12px;color:#be123c;font-weight:700}.btn[disabled]{opacity:.58;cursor:not-allowed}.cart-link{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border-radius:999px;background:rgba(255,255,255,.12)}.cart-shell{padding:30px 0 96px;background:linear-gradient(180deg,var(--c-page) 0%,#fff 100%);min-height:calc(100vh - 180px)}.cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:30px}.cart-panel,.summary-card,.checkout-card{border:1px solid color-mix(in srgb,var(--c-primary) 13%,#e5e7eb);border-radius:24px;background:var(--c-card);padding:24px;box-shadow:0 14px 42px color-mix(in srgb,var(--c-primary) 7%,transparent)}.cart-line{display:grid;grid-template-columns:90px 1fr auto;gap:16px;align-items:center;padding:18px 0;border-bottom:1px solid #eef2f7}.cart-line:last-child{border-bottom:0}.cart-image{width:90px;height:90px;border-radius:14px;overflow:hidden;background:#f4f6f8}.cart-image img{width:100%;height:100%;object-fit:cover}.cart-title{font-weight:700}.cart-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}.qty{width:78px}.summary-card{height:max-content;position:sticky;top:92px}.summary-row{display:flex;justify-content:space-between;gap:20px;padding:10px 0}.summary-total{font-size:20px;font-weight:700;border-top:1px solid color-mix(in srgb,var(--c-primary) 12%,#e5e7eb);margin-top:10px;padding-top:18px}.purchase-form{display:grid;grid-template-columns:minmax(88px,120px) minmax(180px,1fr);gap:12px;align-items:center;margin-top:24px;max-width:520px}.purchase-form .qty{width:100%;min-height:50px}.purchase-form .btn{min-height:50px;border-radius:14px;font-size:14px;box-shadow:0 10px 24px color-mix(in srgb,var(--c-primary) 22%,transparent)}.product-trust{margin-top:24px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.product-trust>span{display:grid;gap:2px;padding:12px 13px;border:1px solid color-mix(in srgb,var(--c-primary) 12%,#e2e8f0);border-radius:14px;background:color-mix(in srgb,var(--c-card) 92%,var(--c-page));min-width:0}.product-trust strong{font-size:12px;color:var(--c-ink)}.product-trust small{font-size:10px;line-height:1.4;color:var(--c-subtle)}.checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:30px}.checkout-card h2,.summary-card h2{margin-top:0}.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:13px;font-weight:800}.field input,.field select{width:100%;border:1px solid #dbe2ea;border-radius:12px;min-height:46px;padding:0 13px;font:inherit}.account-wrap{max-width:920px}.account-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:22px}.order-card{display:grid;grid-template-columns:1fr auto;gap:18px;padding:18px 0;border-bottom:1px solid #eef2f7}.order-card:last-child{border-bottom:0}.status-pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:12px;font-weight:700;text-transform:capitalize}.auth-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px}.auth-card{border:1px solid #e5e7eb;border-radius:22px;background:#fff;padding:26px}.detail-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px}.footer{background:#0f172a;color:#cbd5e1;padding:34px 0}.footer-inner{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;font-size:14px}
        .commerce-runtime{background:linear-gradient(180deg,var(--c-page) 0%,color-mix(in srgb,var(--c-primary) 7%,#fff) 100%);padding-top:34px}.runtime-intro{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:28px}.runtime-intro h1{margin:0;font-size:clamp(34px,4.6vw,54px);line-height:1.04;letter-spacing:-.045em;font-weight:700}.runtime-intro p{margin:10px 0 0;max-width:650px;color:#64748b}.runtime-kicker,.summary-eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:10px;font-weight:700;color:var(--c-primary);margin-bottom:8px}.runtime-continue{flex:0 0 auto;display:inline-grid;place-items:center;min-height:42px;padding:0 16px;line-height:1.2}.commerce-runtime--cart .cart-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px}.commerce-runtime--cart .cart-actions form{display:flex;align-items:center;gap:8px;margin:0}.commerce-runtime--cart .cart-actions .qty{width:68px;min-height:38px;height:38px;flex:0 0 68px;padding:0 10px}.commerce-runtime--cart .cart-actions .btn{min-height:38px;height:38px;padding:0 13px;border-radius:10px;font-size:13px;box-shadow:none}.runtime-primary-action{display:grid;place-items:center;margin-top:18px;width:100%;min-height:50px;border-radius:14px}.commerce-runtime .cart-panel,.commerce-runtime .summary-card,.commerce-runtime .checkout-card{box-shadow:0 18px 50px rgba(15,23,42,.055);border-color:color-mix(in srgb,var(--c-primary) 12%,#e5e7eb)}.commerce-runtime .summary-card{overflow:hidden}.commerce-runtime .summary-card h2{font-size:23px;letter-spacing:-.03em;margin-bottom:12px}.cart-visual--split .cart-layout{grid-template-columns:minmax(0,1.2fr) minmax(330px,.8fr);gap:34px}.cart-visual--split .summary-card{background:linear-gradient(160deg,color-mix(in srgb,var(--c-primary) 8%,#fff),#fff)}.cart-visual--classic .cart-layout{grid-template-columns:minmax(0,1fr) 340px}.cart-visual--classic .cart-panel{box-shadow:none}.cart-visual--compact .cart-shell{padding-top:14px}.cart-visual--compact .runtime-intro{margin-bottom:18px}.cart-visual--compact .cart-layout{grid-template-columns:minmax(0,1fr) 310px;gap:20px}.cart-visual--compact .cart-panel,.cart-visual--compact .summary-card{padding:16px;border-radius:18px}.cart-visual--compact .cart-line{grid-template-columns:68px 1fr auto;padding:12px 0;gap:12px}.cart-visual--compact .cart-image{width:68px;height:68px}.cart-visual--compact .cart-actions{margin-top:7px}.checkout-visual--split .checkout-grid{grid-template-columns:minmax(0,1.12fr) minmax(350px,.88fr);gap:36px}.checkout-visual--split .summary-card{background:linear-gradient(160deg,color-mix(in srgb,var(--c-primary) 8%,#fff),#fff)}.checkout-visual--classic .checkout-grid{grid-template-columns:minmax(0,1fr) 360px}.checkout-visual--express .runtime-intro{max-width:820px}.checkout-visual--express .checkout-grid{grid-template-columns:minmax(0,760px);justify-content:center}.checkout-visual--express .summary-card{position:static;order:-1}.checkout-visual--express .checkout-card,.checkout-visual--express .summary-card{border-radius:18px;box-shadow:0 12px 34px rgba(15,23,42,.05)}.checkout-visual--express .field-grid{gap:11px}.checkout-visual--express .field input,.checkout-visual--express .field select{min-height:44px}.commerce-runtime .field input,.commerce-runtime .field select,.commerce-runtime .input,.commerce-runtime .select{background:#fff;color:#0f172a}.commerce-runtime .btn:focus-visible,.commerce-runtime input:focus-visible,.commerce-runtime select:focus-visible{outline:3px solid color-mix(in srgb,var(--c-primary) 24%,transparent);outline-offset:2px}.runtime-empty{background:#fff}.cosmic-commerce-site-shell+main .breadcrumb{padding-top:22px}

        .cosmic-mini-cart-trigger{position:fixed;right:22px;top:50%;transform:translateY(-50%);z-index:70;width:58px;height:58px;border:0;border-radius:999px;background:var(--c-primary);color:#fff;display:grid;place-items:center;box-shadow:0 18px 42px rgba(15,23,42,.24);cursor:pointer}.cosmic-mini-cart-trigger:hover{transform:translateY(-50%) scale(1.04)}.cosmic-mini-cart-trigger svg{width:24px;height:24px}.cosmic-mini-cart-count{position:absolute;right:-3px;top:-3px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#fff;color:var(--c-primary);display:grid;place-items:center;font-size:11px;font-weight:800;box-shadow:0 4px 12px rgba(15,23,42,.18)}
        .cosmic-cart-backdrop{position:fixed;inset:0;z-index:79;background:rgba(15,23,42,.46);backdrop-filter:blur(3px);opacity:0;pointer-events:none;transition:opacity .2s ease}.cosmic-cart-backdrop.is-open{opacity:1;pointer-events:auto}.cosmic-cart-drawer{position:fixed;inset:0 0 0 auto;z-index:80;width:min(430px,calc(100vw - 24px));background:#fff;color:#0f172a;box-shadow:-28px 0 70px rgba(15,23,42,.22);transform:translateX(102%);transition:transform .24s ease;display:flex;flex-direction:column}.cosmic-cart-drawer.is-open{transform:translateX(0)}.cosmic-cart-drawer-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px;border-bottom:1px solid #e2e8f0}.cosmic-cart-drawer-head h2{margin:0;font-size:22px}.cosmic-cart-close{border:0;background:#f1f5f9;color:#0f172a;width:38px;height:38px;border-radius:999px;font-size:20px;cursor:pointer}.cosmic-cart-drawer-body{padding:8px 22px 22px;overflow:auto;flex:1}.cosmic-cart-item{display:grid;grid-template-columns:1fr auto;gap:12px;padding:18px 0;border-bottom:1px solid #edf2f7}.cosmic-cart-item-title{font-weight:800}.cosmic-cart-item-meta{font-size:12px;color:#64748b;margin-top:3px}.cosmic-cart-item-controls{display:flex;align-items:center;gap:7px;margin-top:10px}.cosmic-cart-item-controls button{border:1px solid #dbe2ea;background:#fff;border-radius:10px;min-width:34px;height:34px;font-weight:800;cursor:pointer}.cosmic-cart-item-controls .remove{padding:0 10px;color:#be123c}.cosmic-cart-item-total{font-weight:800;white-space:nowrap}.cosmic-cart-empty{padding:52px 6px;text-align:center;color:#64748b}.cosmic-cart-drawer-foot{border-top:1px solid #e2e8f0;padding:20px 22px 24px}.cosmic-cart-subtotal{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:17px;margin-bottom:14px}.cosmic-cart-actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.cosmic-cart-action{min-height:46px;border-radius:12px;display:grid;place-items:center;font-weight:800;border:1px solid #dbe2ea;background:#fff;color:#0f172a}.cosmic-cart-action.primary{background:var(--c-primary);border-color:var(--c-primary);color:#fff}.cosmic-commerce-toast{position:fixed;left:50%;bottom:28px;z-index:90;transform:translate(-50%,18px);opacity:0;background:#0f172a;color:#fff;border-radius:999px;padding:11px 16px;font-size:13px;font-weight:800;box-shadow:0 15px 38px rgba(15,23,42,.25);transition:.2s ease;pointer-events:none}.cosmic-commerce-toast.is-visible{transform:translate(-50%,0);opacity:1}
        @media(max-width:640px){.cosmic-mini-cart-trigger{right:14px;top:auto;bottom:18px;transform:none}.cosmic-mini-cart-trigger:hover{transform:scale(1.03)}.cosmic-cart-drawer{width:min(100%,430px)}.cosmic-cart-actions-grid{grid-template-columns:1fr}}
        @media(max-width:900px){.catalog-layout,.cart-layout,.checkout-grid,.auth-grid,.detail-grid,.cart-visual--split .cart-layout,.cart-visual--compact .cart-layout,.checkout-visual--split .checkout-grid,.checkout-visual--classic .checkout-grid{grid-template-columns:1fr}.runtime-intro{align-items:flex-start;flex-direction:column}.runtime-continue{width:max-content}.summary-card{position:static}.filter-card{position:static}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.product-main{grid-template-columns:1fr;gap:34px}.product-info{position:static;padding-top:4px}.navlinks{display:none}}@media(max-width:700px){.product-trust{grid-template-columns:1fr}.product-badges{top:12px;left:12px}}@media(max-width:580px){.wrap{width:min(100% - 24px,1180px)}.commerce-runtime{padding-top:20px}.runtime-intro{margin-bottom:20px}.runtime-intro h1{font-size:34px}.runtime-continue{width:100%;display:grid;place-items:center}.cart-line,.cart-visual--compact .cart-line{grid-template-columns:62px 1fr;align-items:start}.cart-line-total{grid-column:2}.cart-image,.cart-visual--compact .cart-image{width:62px;height:62px}.cart-actions form{display:flex;gap:6px}.commerce-runtime--cart .cart-actions{gap:7px}.commerce-runtime--cart .cart-actions .qty{width:62px;flex-basis:62px}.commerce-runtime--cart .cart-actions .btn{padding:0 11px;font-size:12px}.field-grid{grid-template-columns:1fr}.field{grid-column:1/-1}.hero{padding:50px 0}.grid{grid-template-columns:1fr}.toolbar,.search{align-items:stretch}.select{width:100%}.product-shell{padding-top:12px}.product-info h1{font-size:36px}.thumbs{grid-template-columns:repeat(4,minmax(0,1fr))}.purchase-form{grid-template-columns:96px 1fr}}
    </style>
</head>
<body class="commerce-page-style-<?php echo e($commerceVisual['page_style'] ?? 'auto'); ?> <?php echo e(($commerceVisual['overlay'] ?? false) ? 'commerce-overlay-header' : ''); ?>">
<?php if($useSiteShell ?? false): ?>
<div class="cosmic-commerce-site-shell"><?php echo $siteHeaderHtml; ?></div>
<?php else: ?>
<header class="topbar"><div class="wrap nav"><a class="brand" href="<?php echo e($shopUrl); ?>"><?php echo e($website->name); ?></a><nav class="navlinks"><a href="<?php echo e($shopUrl); ?>">Shop</a><?php $__currentLoopData = $categories->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $navCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($categoryUrl($navCategory)); ?>"><?php echo e($navCategory->name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e($accountUrl); ?>">Account</a><a class="cart-link" href="<?php echo e($cartUrl); ?>">Cart <strong><?php echo e($cartCount); ?></strong></a></nav></div></header>
<?php endif; ?>
<?php
    $commerceHeroEyebrow = match ($viewMode) {
        'product' => 'Product', 'cart' => 'Cart', 'checkout' => 'Checkout', 'order-success' => 'Order confirmed', 'category' => 'Collection', default => 'Shop'
    };
    $commerceHeroTitle = $viewMode === 'product' ? $product->title : ($title ?? $website->name);
    $commerceHeroDescription = $viewMode === 'product' ? ($product->short_description ?: 'Explore product details, options and availability.') : ($description ?? '');
?>
<section class="commerce-global-hero" data-cosmic-mini-hero="true" data-cosmic-dynamic-page-style="<?php echo e($commerceVisual['page_style'] ?? 'auto'); ?>" data-cosmic-header-overlay="<?php echo e(($commerceVisual['overlay'] ?? false) ? 'true' : 'false'); ?>"><div class="wrap"><div class="eyebrow" style="color:inherit;opacity:.72"><?php echo e($commerceHeroEyebrow); ?></div><h1><?php echo e($commerceHeroTitle); ?></h1><?php if(filled($commerceHeroDescription)): ?><p><?php echo e($commerceHeroDescription); ?></p><?php endif; ?></div></section>
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

<?php if($commerceEnabled ?? false): ?>
<button type="button" class="cosmic-mini-cart-trigger" data-cosmic-mini-cart-open aria-label="Open cart" aria-controls="cosmic-cart-drawer" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h2l2.2 10.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 7H6"/><circle cx="10" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/></svg>
    <span class="cosmic-mini-cart-count" data-cosmic-cart-count><?php echo e($cartCount); ?></span>
</button>
<div class="cosmic-cart-backdrop" data-cosmic-cart-backdrop></div>
<aside id="cosmic-cart-drawer" class="cosmic-cart-drawer" data-cosmic-cart-drawer aria-hidden="true" aria-label="Shopping cart">
    <div class="cosmic-cart-drawer-head"><div><div class="eyebrow" style="margin:0 0 4px">Your cart</div><h2>Shopping bag</h2></div><button type="button" class="cosmic-cart-close" data-cosmic-cart-close aria-label="Close cart">×</button></div>
    <div class="cosmic-cart-drawer-body" data-cosmic-cart-body><div class="cosmic-cart-empty">Loading cart…</div></div>
    <div class="cosmic-cart-drawer-foot">
        <div class="cosmic-cart-subtotal"><span>Subtotal</span><strong data-cosmic-cart-subtotal>—</strong></div>
        <div class="cosmic-cart-actions-grid"><a class="cosmic-cart-action" href="<?php echo e($cartUrl); ?>">View cart</a><a class="cosmic-cart-action primary" href="<?php echo e($checkoutUrl); ?>">Checkout</a></div>
    </div>
</aside>
<div class="cosmic-commerce-toast" data-cosmic-commerce-toast role="status" aria-live="polite"></div>
<?php endif; ?>

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


    <?php if($commerceEnabled ?? false): ?>
    (function () {
        var trigger = document.querySelector('[data-cosmic-mini-cart-open]');
        var drawer = document.querySelector('[data-cosmic-cart-drawer]');
        var backdrop = document.querySelector('[data-cosmic-cart-backdrop]');
        var closeButton = document.querySelector('[data-cosmic-cart-close]');
        var body = document.querySelector('[data-cosmic-cart-body]');
        var count = document.querySelector('[data-cosmic-cart-count]');
        var subtotal = document.querySelector('[data-cosmic-cart-subtotal]');
        var toast = document.querySelector('[data-cosmic-commerce-toast]');
        var summaryUrl = <?php echo json_encode($cartSummaryUrl, 15, 512) ?>;
        var updateUrl = <?php echo json_encode($cartUpdateUrl, 15, 512) ?>;
        var removeUrl = <?php echo json_encode($cartRemoveUrl, 15, 512) ?>;
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        var lastFocus = null;
        var state = null;

        function money(minor, currency, decimals) {
            var places = Number.isInteger(decimals) ? decimals : 2;
            try { return new Intl.NumberFormat(document.documentElement.lang || 'en', {style:'currency',currency:currency || 'USD'}).format(Number(minor || 0) / Math.pow(10, places)); }
            catch (error) { return (currency || 'USD') + ' ' + (Number(minor || 0) / Math.pow(10, places)).toFixed(places); }
        }
        function esc(value) { var div=document.createElement('div'); div.textContent=String(value ?? ''); return div.innerHTML; }
        function notify(message) { if (!toast) return; toast.textContent=message || 'Cart updated.'; toast.classList.add('is-visible'); clearTimeout(notify.timer); notify.timer=setTimeout(function(){toast.classList.remove('is-visible');},2200); }
        function openDrawer() { if (!drawer || !backdrop) return; lastFocus=document.activeElement; drawer.classList.add('is-open'); backdrop.classList.add('is-open'); drawer.setAttribute('aria-hidden','false'); trigger?.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; closeButton?.focus(); }
        function closeDrawer() { if (!drawer || !backdrop) return; drawer.classList.remove('is-open'); backdrop.classList.remove('is-open'); drawer.setAttribute('aria-hidden','true'); trigger?.setAttribute('aria-expanded','false'); document.body.style.overflow=''; if (lastFocus && typeof lastFocus.focus==='function') lastFocus.focus(); }
        function render(data) {
            state=data || {}; if(count) count.textContent=String(state.count || 0); if(subtotal) subtotal.textContent=money(state.subtotal_minor,state.currency,state.decimals);
            if(!body) return; var items=Array.isArray(state.items)?state.items:[];
            if(!items.length){body.innerHTML='<div class="cosmic-cart-empty"><strong>Your cart is empty</strong><div style="margin-top:6px">Add a product to get started.</div></div>';return;}
            body.innerHTML=items.map(function(item){return '<div class="cosmic-cart-item" data-line="'+esc(item.line)+'"><div><a class="cosmic-cart-item-title" href="'+esc(item.product_url || '#')+'">'+esc(item.title)+'</a>'+(item.option_label?'<div class="cosmic-cart-item-meta">'+esc(item.option_label)+'</div>':'')+'<div class="cosmic-cart-item-controls"><button type="button" data-cart-dec aria-label="Decrease quantity">−</button><span>'+esc(item.quantity)+'</span><button type="button" data-cart-inc aria-label="Increase quantity">+</button><button type="button" class="remove" data-cart-remove>Remove</button></div></div><div class="cosmic-cart-item-total">'+esc(money(item.line_total_minor,state.currency,state.decimals))+'</div></div>';}).join('');
        }
        function request(url, data) { var formData=new FormData(); Object.keys(data || {}).forEach(function(k){formData.append(k,data[k]);}); if(csrf) formData.append('_token',csrf); return fetch(url,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:formData,credentials:'same-origin'}).then(async function(response){var payload=await response.json().catch(function(){return {};}); if(!response.ok) throw new Error(payload.message || Object.values(payload.errors || {})?.flat?.()[0] || 'Unable to update cart.'); return payload;}); }
        function refresh() { return fetch(summaryUrl,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'}).then(function(r){return r.ok?r.json():Promise.reject(new Error('Unable to load cart.'));}).then(function(data){render(data);return data;}); }
        trigger?.addEventListener('click',function(){openDrawer();refresh().catch(function(e){notify(e.message);});}); closeButton?.addEventListener('click',closeDrawer); backdrop?.addEventListener('click',closeDrawer);
        document.addEventListener('keydown',function(e){if(!drawer?.classList.contains('is-open'))return;if(e.key==='Escape'){closeDrawer();return;}if(e.key==='Tab'){var focusable=[...drawer.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex=\"-1\"])')].filter(function(el){return el.offsetParent!==null;});if(!focusable.length)return;var first=focusable[0],last=focusable[focusable.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
        document.querySelectorAll('[data-commerce-add-form]').forEach(function(form){form.addEventListener('submit',function(event){event.preventDefault();if(!form.checkValidity())return;var button=form.querySelector('button[type="submit"]');if(button?.disabled)return;var original=button?.textContent||'Add to cart';if(button){button.disabled=true;button.textContent='Adding…';}fetch(form.action,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form),credentials:'same-origin'}).then(async function(response){var payload=await response.json().catch(function(){return {};});if(!response.ok)throw new Error(payload.message || Object.values(payload.errors || {})?.flat?.()[0] || 'Unable to add to cart.');render(payload);notify(payload.message || 'Added to cart.');openDrawer();}).catch(function(error){notify(error.message);}).finally(function(){if(button){button.disabled=false;button.textContent=original;}});});});
        body?.addEventListener('click',function(event){var row=event.target.closest('[data-line]');if(!row||!state)return;var item=(state.items||[]).find(function(x){return x.line===row.dataset.line;});if(!item)return;if(event.target.closest('[data-cart-remove]')){request(removeUrl,{line:item.line}).then(function(data){render(data);notify(data.message||'Item removed.');}).catch(function(e){notify(e.message);});return;}var delta=event.target.closest('[data-cart-inc]')?1:(event.target.closest('[data-cart-dec]')?-1:0);if(!delta)return;var next=Math.max(0,Math.min(Number(item.max_quantity||99),Number(item.quantity||1)+delta));request(updateUrl,{line:item.line,quantity:next}).then(function(data){render(data);}).catch(function(e){notify(e.message);});});
        refresh().catch(function(){});
    })();
    <?php endif; ?>

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