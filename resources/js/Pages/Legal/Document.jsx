import { Head, Link } from '@inertiajs/react';

const sections = {
  terms: [
    ['Using Cosmic CMS', 'You must provide accurate account information, keep credentials secure, and use the service only for lawful website creation and management.'],
    ['Subscriptions and credits', 'Plan access, website limits, credits, renewals, cancellations, and refunds follow the pricing and billing terms shown at checkout.'],
    ['Customer content', 'You retain ownership of content you upload. You grant Cosmic CMS the limited rights needed to host, process, publish, and back up that content.'],
    ['Acceptable use', 'You may not abuse the platform, bypass limits, disrupt infrastructure, upload unlawful content, or infringe third-party rights.'],
    ['Availability and liability', 'The service is provided on an as-available basis. Backups and safeguards reduce risk but do not replace your own business continuity plan.'],
  ],
  privacy: [
    ['Information collected', 'We process account details, workspace and website content, billing references, support communications, usage events, and security logs.'],
    ['Why information is used', 'Information is used to operate the platform, process payments, prevent abuse, provide support, improve reliability, and meet legal obligations.'],
    ['Sharing and processors', 'Data may be shared with infrastructure, email, analytics, AI, and payment providers only as needed to deliver the service.'],
    ['Retention and security', 'Retention depends on account status, legal obligations, backup cycles, and security needs. Administrative, technical, and organizational controls are applied.'],
    ['Your choices', 'You may update account details, manage optional notifications and cookies, export supported data, or request account deletion subject to legal retention requirements.'],
  ],
  cookies: [
    ['Necessary storage', 'Required storage supports authentication, security, preferences, checkout continuity, and core platform functions. It cannot be disabled through the consent banner.'],
    ['Analytics', 'Optional analytics helps measure product usage, performance, and conversion. Analytics remains disabled until consent is granted.'],
    ['Marketing', 'Optional marketing storage may be used for campaign attribution or relevant communications. It remains disabled until consent is granted.'],
    ['Changing consent', 'You can clear the saved browser preference to make the consent banner appear again. A new policy version also requests consent again.'],
  ],
};

export default function Document({ title, type, version, effectiveDate, companyName, contactEmail }) {
  return <><Head title={title} /><main className="min-h-screen bg-slate-950 px-5 py-12 text-slate-200"><div className="mx-auto max-w-3xl"><Link href="/" className="text-sm font-semibold text-violet-300">← Back to {companyName}</Link><div className="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-6 sm:p-10"><p className="text-xs font-semibold uppercase tracking-[0.2em] text-violet-300">Legal</p><h1 className="mt-3 text-3xl font-black text-white sm:text-4xl">{title}</h1><p className="mt-3 text-sm text-slate-400">Effective {effectiveDate} · Version {version}</p><div className="mt-8 space-y-8">{sections[type].map(([heading, body]) => <section key={heading}><h2 className="text-lg font-semibold text-white">{heading}</h2><p className="mt-2 leading-7 text-slate-300">{body}</p></section>)}</div><p className="mt-10 border-t border-white/10 pt-6 text-sm text-slate-400">Questions may be sent to {contactEmail || 'the support address shown in your account'}.</p></div></div></main></>;
}
