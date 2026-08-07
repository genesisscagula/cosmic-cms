import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const fmtWait = (seconds) => seconds < 60 ? `${seconds}s` : `${Math.floor(seconds/60)}m`;

export default function QueueDashboard(props) {
  const [data, setData] = useState(props);
  useEffect(() => {
    const timer = setInterval(async () => {
      try { const r = await fetch(route('admin.queues.status'), { headers: { Accept: 'application/json' } }); if (r.ok) setData(await r.json()); } catch (_) {}
    }, 5000);
    return () => clearInterval(timer);
  }, []);
  const cards = [
    ['Waiting', data.summary?.pending ?? 0], ['Running', data.summary?.running ?? 0],
    ['Failed', data.summary?.failed ?? 0], ['Stale', data.summary?.stale ?? 0],
  ];
  return <AuthenticatedLayout>
    <Head title="Queue Dashboard" />
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      <div className="mb-7 flex flex-wrap items-center justify-between gap-4">
        <div><p className="text-sm font-semibold text-emerald-600">COSMIC OPERATIONS</p><h1 className="text-3xl font-bold text-slate-900">Queue Dashboard</h1><p className="mt-1 text-sm text-slate-500">Live Horizon-lite view · refreshes every 5 seconds</p></div>
        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${data.healthy ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>{data.healthy ? 'Healthy' : 'Needs attention'}</span>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{cards.map(([label,value]) => <div key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-sm text-slate-500">{label}</p><p className="mt-2 text-3xl font-bold text-slate-900">{value}</p></div>)}</div>
      <div className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="grid grid-cols-5 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500"><span>Queue</span><span>Running</span><span>Waiting</span><span>Failed</span><span>Oldest wait</span></div>
        {(data.queues || []).map(q => <div key={q.key} className="grid grid-cols-5 items-center border-b border-slate-100 px-5 py-4 text-sm last:border-0"><div><p className="font-semibold text-slate-900">{q.label}</p><p className="text-xs text-slate-400">{q.key}</p></div><span>{q.running}</span><span>{q.waiting}</span><span className={q.failed ? 'font-semibold text-rose-600' : ''}>{q.failed}</span><span>{fmtWait(q.oldest_wait_seconds)}</span></div>)}
      </div>
      <div className="mt-6 flex flex-wrap gap-3"><button onClick={() => router.post(route('admin.queues.retry-failed'))} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Retry failed</button><button onClick={() => window.confirm('Clear failed queue history?') && router.delete(route('admin.queues.forget-failed'))} className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Clear failed history</button></div>
    </div>
  </AuthenticatedLayout>;
}
