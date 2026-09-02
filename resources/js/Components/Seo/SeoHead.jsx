import { Head } from '@inertiajs/react';

const absoluteUrl = (value, baseUrl) => {
    if (!value) return baseUrl;
    if (/^https?:\/\//i.test(value)) return value;
    return `${baseUrl}${value.startsWith('/') ? value : `/${value}`}`;
};

export default function SeoHead({
    title = 'AI Website Builder for Modern Business Websites | Cosmic CMS',
    description = 'Build a modern, responsive business website with Cosmic CMS, an AI website builder for generating, customizing, and publishing professional websites faster.',
    path = '/',
    image = '/images/cosmic-cms-social-preview.png',
    type = 'website',
    noIndex = false,
    schema = null,
    baseUrl = 'https://www.cosmiccms.com',
}) {
    const normalizedBaseUrl = String(baseUrl || 'https://www.cosmiccms.com').replace(/\/$/, '');
    const canonical = absoluteUrl(path, normalizedBaseUrl);
    const socialImage = absoluteUrl(image, normalizedBaseUrl);
    const schemas = Array.isArray(schema) ? schema : (schema ? [schema] : []);

    return (
        <Head title={title}>
            <meta head-key="description" name="description" content={description} />
            <meta head-key="robots" name="robots" content={noIndex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'} />
            <link head-key="canonical" rel="canonical" href={canonical} />

            <meta head-key="og:type" property="og:type" content={type} />
            <meta head-key="og:site_name" property="og:site_name" content="Cosmic CMS" />
            <meta head-key="og:title" property="og:title" content={title} />
            <meta head-key="og:description" property="og:description" content={description} />
            <meta head-key="og:url" property="og:url" content={canonical} />
            <meta head-key="og:image" property="og:image" content={socialImage} />

            <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
            <meta head-key="twitter:title" name="twitter:title" content={title} />
            <meta head-key="twitter:description" name="twitter:description" content={description} />
            <meta head-key="twitter:image" name="twitter:image" content={socialImage} />

            {schemas.map((item, index) => (
                <script
                    key={`seo-schema-${index}`}
                    head-key={`seo-schema-${index}`}
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: JSON.stringify(item) }}
                />
            ))}
        </Head>
    );
}
