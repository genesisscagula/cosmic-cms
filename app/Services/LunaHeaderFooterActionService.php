<?php

namespace App\Services;

use App\Models\Website;
use App\Support\HeaderFooterVariantContract;
use Illuminate\Support\Str;

/**
 * Bounded, zero-credit mutations for the existing global header/footer shell.
 * The Builder remains authoritative for persistence; this service only returns
 * validated shell snapshots using fields already consumed by Builder + exporter.
 */
final class LunaHeaderFooterActionService
{
    public function apply(Website $website, string $prompt, array $intent, array $header = [], array $footer = []): ?array
    {
        if (($intent['intent'] ?? '') !== 'action') return null;

        $scope = (string) data_get($intent, 'routing.menu_scope', '');
        $action = (string) data_get($intent, 'routing.scope_action', '');
        if (! in_array($scope, ['header', 'footer'], true)) return null;

        return $scope === 'header'
            ? $this->applyHeader($website, $prompt, $action, $header)
            : $this->applyFooter($website, $prompt, $action, $footer);
    }

    private function applyHeader(Website $website, string $prompt, string $action, array $header): ?array
    {
        $header = HeaderFooterVariantContract::normalizeHeader($header !== [] ? $header : (is_array($website->global_header) ? $website->global_header : [])) ?? [];
        $lower = Str::lower($prompt);
        $operations = [];

        if (in_array($action, ['custom_header', 'mobile_header'], true)) return null;

        if ($action === 'overlay_header' || $action === 'transparent_header') {
            $enabled = ! preg_match('/\b(disable|off|remove|turn off|not transparent|non[- ]?transparent)\b/i', $prompt);
            $currentType = (string) ($header['type'] ?? 'classic_header');
            $nextType = $enabled
                ? (in_array($currentType, ['centered_header','overlay_centered_header'], true) ? 'overlay_centered_header' : 'overlay_hero_header')
                : ($currentType === 'overlay_centered_header' ? 'centered_header' : 'classic_header');
            $header['type'] = $nextType;
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $nextType);
        } elseif ($action === 'sticky_header') {
            $enabled = ! preg_match('/\b(disable|off|remove|turn off|not sticky)\b/i', $prompt);
            if (! $enabled) return null;
            $currentType = (string) ($header['type'] ?? 'classic_header');
            if ($currentType === 'overlay_centered_header') $header['type'] = 'centered_header';
            elseif ($currentType === 'overlay_hero_header') $header['type'] = 'classic_header';
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $header['type']);
        }

        if ($action === 'change_header') {
            $type = null;
            if (preg_match('/\b(?:dark cyan|dark-cyan|cyan)\b/i', $prompt)) $type = 'dark_cyan_header';
            if (preg_match('/\b(?:glass|glassmorphism|glass morphism)\b/i', $prompt)) $type = 'glassmorphism_header';
            if (preg_match('/\b(?:classic|white)(?: header)?\b/i', $prompt)) $type = 'classic_header';
            if (preg_match('/\bprimary(?: contrast| color| background)?(?: header)?\b/i', $prompt)) $type = 'primary_header';
            if (preg_match('/\b(?:center(?:ed)? logo|split navigation|split nav)(?: with cta)?(?: header)?\b/i', $prompt)) $type = 'split_navigation_header';
            if (preg_match('/\b(?:center(?:ed)? logo|centered)(?: without cta| no cta)?(?: header)?\b/i', $prompt) && ! preg_match('/\bwith cta\b/i', $prompt)) $type = 'centered_header';
            if (preg_match('/\boverlay(?: hero)?(?: header)?\b/i', $prompt)) $type = 'overlay_hero_header';
            if (preg_match('/\boverlay\s+(?:center(?:ed)?|center logo)(?: header)?\b/i', $prompt)) $type = 'overlay_centered_header';
            if (preg_match('/\bsecondary(?: surface| background)?(?: header)?\b/i', $prompt)) $type = 'secondary_header';
            if (preg_match('/\b(?:floating(?: glass)?|minimal)(?: header)?\b/i', $prompt)) $type = 'classic_header';
            if ($type === null) return null;
            $header['type'] = $type;
            $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;
            $operations[] = $this->op('header.type', $header['type']);
        }

        if (in_array($action, ['logo', 'edit_header'], true)) {
            if (preg_match('/\blogo\b.*?\b(?:height|size)\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)
                || preg_match('/\b(?:height|size)\b.*?\blogo\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)) {
                $height = max(44, min(60, (int) $m[1]));
                $header['logo_height'] = $height;
                $operations[] = $this->op('header.logo_height', $height);
            }
            if (preg_match('/\blogo\b.*?\b(?:max[- ]?width|width)\b.*?(\d{2,3})\s*(?:px)?\b/i', $prompt, $m)) {
                $width = max(180, min(300, (int) $m[1]));
                $header['logo_max_width'] = $width;
                $operations[] = $this->op('header.logo_max_width', $width);
            }
            if (preg_match('/\b(?:logo text|brand name)\b\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?$/iu', trim($prompt), $m)) {
                $text = trim($m[1]);
                if ($text !== '') {
                    $header['logo_text'] = Str::limit($text, 80, '');
                    $operations[] = $this->op('header.logo_text', $header['logo_text']);
                }
            }
        }

        if (in_array($action, ['header_cta', 'edit_header'], true)) {
            if (preg_match('/\b(?:cta|button)\s+(?:label|text)\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?/iu', $prompt, $m)) {
                $label = trim(preg_replace('/\s+(?:and|with)\s+(?:url|link).*$/iu', '', $m[1]) ?? $m[1]);
                if ($label !== '') {
                    $header['cta_label'] = Str::limit($label, 60, '');
                    $operations[] = $this->op('header.cta_label', $header['cta_label']);
                }
            }
            if (preg_match('/\b(?:cta|button)\s+(?:url|link)\s*(?:to|as|:)\s*["“]?([^\s"”]+)["”]?/iu', $prompt, $m)) {
                $url = $this->safeUrl($m[1]);
                if ($url !== null) {
                    $header['cta_url'] = $url;
                    $operations[] = $this->op('header.cta_url', $url);
                }
            }
        }

        if ($action === 'header_spacing') {
            // Existing header variants do not expose generic shell padding tokens.
            // Custom-shell settings own exact height/padding, so only mutate them
            // when the current header already opted into that renderer contract.
            if (! ($header['custom_shell_mode'] ?? false)) return null;
            $style = is_array($header['custom_style'] ?? null) ? $header['custom_style'] : [];
            if (preg_match('/\bheader\s+height\b.*?(\d{2,3})\s*px\b/i', $prompt, $m)) {
                $style['height'] = max(48, min(180, (int) $m[1]));
                $operations[] = $this->op('header.custom_style.height', $style['height']);
            }
            if (preg_match('/\b(?:horizontal\s+)?padding\b.*?(\d{1,3})\s*px\b/i', $prompt, $m)) {
                $style['padding_x'] = max(16, min(160, (int) $m[1]));
                $operations[] = $this->op('header.custom_style.padding_x', $style['padding_x']);
            }
            if ($operations !== []) $header['custom_style'] = $style;
        }

        return $operations === [] ? null : $this->success('header', $action ?: 'edit_header', ['header' => HeaderFooterVariantContract::normalizeHeader($header) ?? $header, 'operations' => $operations]);
    }

    private function applyFooter(Website $website, string $prompt, string $action, array $footer): ?array
    {
        $footer = HeaderFooterVariantContract::normalizeFooter($footer !== [] ? $footer : (is_array($website->global_footer) ? $website->global_footer : [])) ?? [];
        $operations = [];
        $mega = is_array($footer['mega_footer'] ?? null) ? $footer['mega_footer'] : [];

        if ($action === 'custom_footer') return null;

        if ($action === 'mega_footer') {
            // Mega Footer is no longer an independent on/off setting. A request
            // to simplify it selects the canonical white footer instead.
            $disableRequested = (bool) preg_match('/\b(disable|off|remove|turn off)\b/i', $prompt);
            $mega['variant'] = $disableRequested ? 'classic' : (string) ($mega['variant'] ?? 'classic');
            $footer['mega_footer'] = $mega;
            $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
            $mega = $footer['mega_footer'];
            $operations[] = $this->op('footer.mega_footer.variant', $mega['variant']);
        }
        if ($action === 'change_footer') {
            $variant = null;
            if (preg_match('/\b(?:mega\s+)?(?:classic|white)(?:\s+footer)?\b/i', $prompt)) $variant = 'classic';
            if (preg_match('/\b(?:mega\s+)?primary(?:\s+footer)?\b/i', $prompt)) $variant = 'primary';
            if (preg_match('/\bcenter(?:ed)?(?:\s+footer)?\s+(?:with\s+)?cta\b|\bcentered[_ -]?cta\b/i', $prompt)) $variant = 'centered_cta';
            if (preg_match('/\b(?:mega\s+)?centered(?: minimal)?(?:\s+footer)?\b/i', $prompt) && ! preg_match('/\bcta\b/i', $prompt)) $variant = 'centered';
            if (preg_match('/\b(?:split|editorial)(?:\s+footer)?\b/i', $prompt)) $variant = 'split';
            if (preg_match('/\b(?:mega\s+)?brand(?:\s+footer)?\b/i', $prompt)) $variant = 'brand';
            if (preg_match('/\bsecondary(?: surface| background)?(?:\s+footer)?\b/i', $prompt)) $variant = 'secondary';
            // Legacy names migrate to the closest current design instead of creating stale variants.
            if (preg_match('/\b(?:contact|newsletter|cta)(?:\s+footer)?\b/i', $prompt) && $variant === null) $variant = 'centered_cta';
            if ($variant !== null) {
                $footer['mega_enabled'] = true;
                $mega['enabled'] = true;
                $mega['variant'] = $variant;
                $footer['mega_footer'] = $mega;
                $operations[] = $this->op('footer.mega_footer.variant', $variant);
            }
        }

        if (in_array($action, ['simple_footer', 'change_footer'], true)) {
            if ($action === 'change_footer' && preg_match('/\bmega\b/i', $prompt)) {
                $footer['mega_enabled'] = true; $mega['enabled'] = true;
                $operations[] = $this->op('footer.mega_enabled', true);
            } elseif ($action === 'simple_footer' || preg_match('/\b(simple|minimal)\b/i', $prompt)) {
                $mega['variant'] = 'centered';
                $footer['mega_footer'] = $mega;
                $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
                $mega = $footer['mega_footer'];
                $operations[] = $this->op('footer.mega_footer.variant', 'centered');
            } elseif ($operations === []) return null;
            $footer['mega_footer'] = $mega;
        }

        if ($action === 'footer_columns') {
            $count = null;
            if (preg_match('/\b([1-4])\s+columns?\b/i', $prompt, $m)) $count = (int) $m[1];
            elseif (preg_match('/\bcolumns?\s*(?:to|:)?\s*([1-4])\b/i', $prompt, $m)) $count = (int) $m[1];
            if ($count === null) return null;
            $columns = is_array($mega['columns'] ?? null) ? array_values($mega['columns']) : [];
            $defaults = [
                ['title'=>'Company','items'=>[['label'=>'About us','url'=>'#about'],['label'=>'Contact','url'=>'#contact']]],
                ['title'=>'Services','items'=>[['label'=>'What we do','url'=>'#services'],['label'=>'Pricing','url'=>'#pricing']]],
                ['title'=>'Resources','items'=>[['label'=>'Insights','url'=>'#insights'],['label'=>'Updates','url'=>'#updates']]],
                ['title'=>'Connect','items'=>[['label'=>'Contact','url'=>'#contact']]],
            ];
            while (count($columns) < $count) $columns[] = $defaults[count($columns)] ?? end($defaults);
            $columns = array_slice($columns, 0, $count);
            $footer['mega_enabled'] = true; $mega['enabled'] = true; $mega['columns'] = $columns; $footer['mega_footer'] = $mega;
            $operations[] = $this->op('footer.mega_footer.columns', $columns);
        }

        if (in_array($action, ['footer_cta', 'edit_footer'], true)) {
            if (preg_match('/\b(?:footer\s+)?(?:cta|button)\s+(?:label|text)\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?/iu', $prompt, $m)) {
                $label = trim(preg_replace('/\s+(?:and|with)\s+(?:url|link).*$/iu', '', $m[1]) ?? $m[1]);
                if ($label !== '') { $mega['primary_label'] = Str::limit($label, 60, ''); $operations[]=$this->op('footer.mega_footer.primary_label',$mega['primary_label']); }
            }
            if (preg_match('/\b(?:footer\s+)?(?:cta|button)\s+(?:url|link)\s*(?:to|as|:)\s*["“]?([^\s"”]+)["”]?/iu', $prompt, $m)) {
                $url=$this->safeUrl($m[1]); if($url!==null){$mega['primary_url']=$url;$operations[]=$this->op('footer.mega_footer.primary_url',$url);}
            }
            if ($operations !== []) { $footer['mega_enabled']=true; $mega['enabled']=true; $footer['mega_footer']=$mega; }
        }

        if ($action === 'footer_background') {
            $theme = null;
            foreach (['primary','white','surface','secondary','auto'] as $candidate) if (preg_match('/\b'.preg_quote($candidate,'/').'\b/i',$prompt)) { $theme=$candidate; break; }
            if ($theme === null) return null;
            if ($theme !== 'auto') {
                $mega['variant'] = match ($theme) {
                    'primary' => 'primary',
                    'surface' => 'split',
                    'secondary' => 'secondary',
                    default => 'classic',
                };
            }
            $footer['mega_footer']=$mega;
            $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;
            $mega = $footer['mega_footer'];
            $operations[]=$this->op('footer.mega_footer.variant',$mega['variant']);
        }

        if ($action === 'footer_brand') {
            if (preg_match('/\bcopyright\s*(?:to|as|:)\s*["“]?([^"”]+)["”]?$/iu',trim($prompt),$m)) {
                $copyright=trim($m[1]);
                if($copyright!==''){$footer['copyright']=Str::limit($copyright,180,'');$operations[]=$this->op('footer.copyright',$footer['copyright']);}
            }
            if (preg_match('/\b(?:footer\s+)?logo\s+(?:height|size)\b.*?(\d{2,3})\s*(?:px)?\b/i',$prompt,$m)) {
                $height=max(24,min(80,(int)$m[1]));$footer['logo_height']=$height;$operations[]=$this->op('footer.logo_height',$height);
            }
        }

        if ($action === 'footer_socials') {
            $links=is_array($footer['social_links']??null)?array_values($footer['social_links']):[];
            $known=['facebook','instagram','linkedin','youtube','tiktok','twitter','x'];
            $added=false;
            foreach($known as $network){
                if(!preg_match('/\b'.preg_quote($network,'/').'\b/i',$prompt)) continue;
                $label=$network==='x'?'X':Str::title($network);
                if(preg_match('/\b'.preg_quote($network,'/').'\b.*?(https?:\/\/[^\s"”]+)/i',$prompt,$m)){
                    $url=$this->safeUrl($m[1]);
                    if($url!==null){
                        $existing=null;foreach($links as $i=>$item){if(Str::lower((string)($item['label']??''))===Str::lower($label)){$existing=$i;break;}}
                        $entry=['label'=>$label,'url'=>$url];
                        if($existing===null)$links[]=$entry;else $links[$existing]=$entry;
                        $added=true;
                    }
                }
            }
            if(!$added) return null; // Never invent social URLs.
            $footer['social_links']=array_slice($links,0,6);
            $operations[]=$this->op('footer.social_links',$footer['social_links']);
        }

        return $operations === [] ? null : $this->success('footer', $action ?: 'edit_footer', ['footer'=>HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer,'operations'=>$operations]);
    }

    private function safeUrl(string $value): ?string
    {
        $url=trim($value," \t\n\r\0\x0B.,;\"");
        if($url==='' || strlen($url)>500) return null;
        if(str_starts_with($url,'/') || str_starts_with($url,'#')) return $url;
        return filter_var($url,FILTER_VALIDATE_URL) ? $url : null;
    }

    private function op(string $target, mixed $value): array
    {
        return ['action'=>'update','target'=>$target,'value'=>$value,'verified'=>true];
    }

    private function success(string $domain,string $action,array $payload): array
    {
        return array_replace(['handled'=>true,'success'=>true,'domain'=>$domain,'scope_action'=>$action,'operations'=>[]],$payload);
    }
}
