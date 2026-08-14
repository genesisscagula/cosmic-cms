import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';
import { showCosmicNotification } from '../../../Components/CosmicNotification';

function Initials({ name = '', email = '' }) {
    const value = (name || email).split(/\s+/).map((part) => part[0]).join('').slice(0, 2).toUpperCase();
    return <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-400/10 text-sm font-bold text-violet-200">{value || '?'}</span>;
}

function PermissionPreview({ role }) {
    if (!role) return null;
    return (
        <div className="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
            <p className="text-sm font-semibold text-white">{role.label}</p>
            <p className="mt-1 text-xs leading-5 text-slate-400">{role.description}</p>
            <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {role.permissions.map((permission) => (
                    <div key={permission.key} className="flex items-center gap-2 text-xs text-slate-300">
                        <span className="text-emerald-300">✓</span>{permission.label}
                    </div>
                ))}
            </div>
        </div>
    );
}

function WebsiteAssignments({ member, websites = [], canManage = false }) {
    const [selected, setSelected] = useState(member.website_ids || []);
    const [saving, setSaving] = useState(false);
    const isOwner = member.role === 'owner';

    const toggle = (websiteId) => {
        setSelected((current) => current.includes(websiteId)
            ? current.filter((id) => id !== websiteId)
            : [...current, websiteId]);
    };

    const save = async (event) => {
        event?.preventDefault?.();
        event?.stopPropagation?.();

        if (saving) return;

        setSaving(true);
        try {
            await axios.put(`/workspace/members/${member.id}/website-assignments`, {
                website_ids: selected,
            }, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            showCosmicNotification({
                title: 'Website access updated',
                message: 'The member’s website assignments were saved successfully.',
                tone: 'success',
            });

            router.reload({ preserveScroll: true });
        } catch (error) {
            const message = error?.response?.data?.message
                || error?.response?.data?.errors?.website_ids?.[0]
                || 'Please review the selected websites and try again.';

            showCosmicNotification({
                title: 'Could not save assignments',
                message,
                tone: 'error',
            });
        } finally {
            setSaving(false);
        }
    };

    if (!websites.length) {
        return <p className="mt-3 text-xs text-slate-500">No websites are available for assignment yet.</p>;
    }

    return (
        <div className="mt-4 rounded-xl border border-white/10 bg-black/20 p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold text-white">Website access</p>
                    <p className="mt-1 text-xs text-slate-500">{isOwner ? 'Owners automatically access every workspace website.' : 'Only selected websites appear in this member’s dashboard.'}</p>
                </div>
                {!isOwner && canManage && (
                    <button type="button" onClick={(event) => save(event)} disabled={saving} className="rounded-lg bg-violet-400/15 px-3 py-2 text-xs font-semibold text-violet-200 hover:bg-violet-400/25 disabled:opacity-50">
                        {saving ? 'Saving…' : 'Save assignments'}
                    </button>
                )}
            </div>
            <div className="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                {websites.map((website) => {
                    const checked = isOwner || selected.includes(website.id);
                    return (
                        <label key={website.id} className={`flex gap-3 rounded-xl border p-3 ${checked ? 'border-violet-300/25 bg-violet-300/10' : 'border-white/10 bg-white/[0.02]'} ${!canManage || isOwner ? 'cursor-default' : 'cursor-pointer'}`}>
                            <input type="checkbox" checked={checked} disabled={!canManage || isOwner} onChange={() => toggle(website.id)} className="mt-1 rounded border-white/20 bg-black/20 text-violet-400" />
                            <span className="min-w-0"><span className="block truncate text-sm font-medium text-white">{website.name}</span><span className="block truncate text-xs text-slate-500">{website.domain} · {website.status}</span></span>
                        </label>
                    );
                })}
            </div>
        </div>
    );
}

export default function Team({ dashboard = {} }) {
    const team = dashboard.team_workspace || {};
    const editableRoles = (team.roles || []).filter((role) => role.key !== 'owner');
    const [selectedRole, setSelectedRole] = useState('editor');
    const form = useForm({ name: '', email: '', role: 'editor', website_ids: [] });
    const limitLabel = team.limit == null ? 'Unlimited' : team.limit;
    const roleMap = useMemo(() => Object.fromEntries((team.roles || []).map((role) => [role.key, role])), [team.roles]);

    const invite = (event) => {
        event.preventDefault();
        form.post(route('workspace.members.store'), { preserveScroll: true, onSuccess: () => form.reset('name', 'email', 'website_ids') });
    };

    const changeInviteRole = (role) => {
        setSelectedRole(role);
        form.setData('role', role);
    };

    const toggleInviteWebsite = (websiteId) => form.setData('website_ids', form.data.website_ids.includes(websiteId) ? form.data.website_ids.filter((id) => id !== websiteId) : [...form.data.website_ids, websiteId]);
    const copyInvitation = async (invitation) => {
        await navigator.clipboard.writeText(invitation.accept_url);
        showCosmicNotification({ title: 'Invitation link copied', message: 'The secure invitation link is ready to share.', tone: 'success' });
    };

    const updateRole = (member, role) => router.patch(route('workspace.members.role.update', member.id), { role }, { preserveScroll: true });
    const removeMember = (member) => {
        if (window.confirm(`Remove ${member.name || member.email} from this workspace?`)) router.delete(route('workspace.members.destroy', member.id), { preserveScroll: true });
    };
    const resendInvitation = (invitation) => router.post(route('workspace.invitations.resend', invitation.id), {}, { preserveScroll: true });
    const cancelInvitation = (invitation) => {
        if (window.confirm(`Cancel the invitation for ${invitation.email}?`)) router.delete(route('workspace.invitations.destroy', invitation.id), { preserveScroll: true });
    };

    if (!team.enabled) {
        return <section><p className="text-sm font-medium text-violet-300">Agency workspace</p><h1 className="mt-2 text-3xl font-semibold text-white">Roles & Permissions</h1><div className="mt-6 rounded-2xl border border-white/10 bg-white/[0.03] p-6"><h2 className="text-lg font-semibold text-white">Team collaboration is locked</h2><p className="mt-2 max-w-2xl text-sm text-slate-400">Starter Agency includes up to 5 team members, Growth Agency up to 10, and Pro Agency unlimited members.</p><a href={route('cosmic-pricing.index')} className="mt-5 inline-flex rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-950">View Agency plans</a></div></section>;
    }

    return (
        <section>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div><p className="text-sm font-medium text-violet-300">Agency workspace</p><h1 className="mt-2 text-3xl font-semibold tracking-tight text-white">Roles, Permissions & Websites</h1><p className="mt-2 text-sm text-slate-400">Control each member’s role and the exact client websites they can access.</p></div>
                <div className="rounded-xl border border-white/10 bg-white/[0.03] px-4 py-3 text-sm"><span className="text-slate-500">Seats used</span><strong className="ml-2 text-white">{team.used || 0} / {limitLabel}</strong></div>
            </div>

            {team.is_owner && <form onSubmit={invite} className="mt-6 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                <div className="grid gap-3 lg:grid-cols-[220px_minmax(0,1fr)_220px_auto] lg:items-end">
                    <label className="text-sm text-slate-300">Name<input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Team member" className="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-violet-400" /></label>
                    <label className="text-sm text-slate-300">Invite by email<input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="client@example.com" className="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-white outline-none focus:border-violet-400" required /></label>
                    <label className="text-sm text-slate-300">Initial role<select value={selectedRole} onChange={(e) => changeInviteRole(e.target.value)} className="mt-2 w-full rounded-xl border border-white/10 bg-[#15151a] px-4 py-3 text-white outline-none focus:border-violet-400">{editableRoles.map((role) => <option key={role.key} value={role.key}>{role.label}</option>)}</select></label>
                    <button disabled={form.processing || team.remaining === 0} className="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:opacity-40">{form.processing ? 'Inviting…' : 'Invite member'}</button>
                </div>
                <div className="mt-4 rounded-xl border border-white/10 bg-black/20 p-4"><p className="text-sm font-semibold text-white">Assign websites now</p><p className="mt-1 text-xs text-slate-500">The selected websites are attached automatically when the invitation is accepted.</p><div className="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">{(team.websites || []).map((website) => <label key={website.id} className={`flex cursor-pointer gap-3 rounded-xl border p-3 ${form.data.website_ids.includes(website.id) ? 'border-violet-300/25 bg-violet-300/10' : 'border-white/10 bg-white/[0.02]'}`}><input type="checkbox" checked={form.data.website_ids.includes(website.id)} onChange={() => toggleInviteWebsite(website.id)} className="mt-1 rounded border-white/20 bg-black/20 text-violet-400" /><span className="min-w-0"><span className="block truncate text-sm font-medium text-white">{website.name}</span><span className="block truncate text-xs text-slate-500">{website.domain}</span></span></label>)}</div></div>
                {(form.errors.name || form.errors.email || form.errors.role || form.errors.website_ids) && <p className="mt-2 text-sm text-rose-300">{form.errors.name || form.errors.email || form.errors.role || form.errors.website_ids}</p>}
                <PermissionPreview role={roleMap[selectedRole]} />
            </form>}

            <div className="mt-6 space-y-4">
                {(team.members || []).length === 0 && <div className="rounded-2xl border border-dashed border-white/10 bg-white/[0.02] p-8 text-center"><p className="font-medium text-white">No team members yet</p><p className="mt-2 text-sm text-slate-500">Invite an admin or editor and assign their websites above.</p></div>}
                {(team.members || []).map((member) => <div key={member.id} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center">
                        <div className="flex min-w-0 flex-1 items-center gap-4"><Initials name={member.name} email={member.email} /><div className="min-w-0"><p className="truncate font-medium text-white">{member.name || member.email}</p><p className="truncate text-sm text-slate-500">{member.email}</p></div></div>
                        {member.role === 'owner' ? <span className="w-fit rounded-full border border-violet-300/20 bg-violet-300/10 px-3 py-1 text-xs font-medium text-violet-200">Owner</span> : team.is_owner ? <select value={member.role} onChange={(e) => updateRole(member, e.target.value)} className="rounded-xl border border-white/10 bg-[#15151a] px-3 py-2 text-sm text-white">{editableRoles.map((role) => <option key={role.key} value={role.key}>{role.label}</option>)}</select> : <span className="w-fit rounded-full border border-white/10 px-3 py-1 text-xs font-medium capitalize text-slate-300">{member.role}</span>}
                        {team.is_owner && member.role !== 'owner' && <button onClick={() => removeMember(member)} className="text-left text-sm text-rose-300 hover:text-rose-200">Remove</button>}
                    </div>
                    <WebsiteAssignments member={member} websites={team.websites || []} canManage={team.is_owner} />
                </div>)}
            </div>

            {(team.invitations || []).length > 0 && <div className="mt-6 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03]"><div className="border-b border-white/10 px-5 py-4"><h2 className="font-semibold text-white">Pending invitations</h2></div><div className="divide-y divide-white/10">{team.invitations.map((invitation) => <div key={invitation.id} className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center"><Initials name={invitation.name} email={invitation.email} /><div className="min-w-0 flex-1"><p className="truncate font-medium text-white">{invitation.name || invitation.email}</p><p className="truncate text-sm text-slate-500">{invitation.email}</p><p className="mt-1 text-xs capitalize text-slate-600">{invitation.role} access · {(invitation.website_ids || []).length} website assignment(s)</p></div><span className="w-fit rounded-full bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-200">Pending</span>{team.is_owner && <button onClick={() => copyInvitation(invitation)} className="text-left text-sm text-violet-300 hover:text-violet-200">Copy invite link</button>}{team.is_owner && <button onClick={() => resendInvitation(invitation)} className="text-left text-sm text-emerald-300 hover:text-emerald-200">Refresh 7 days</button>}{team.is_owner && <button onClick={() => cancelInvitation(invitation)} className="text-left text-sm text-slate-400 hover:text-white">Cancel</button>}</div>)}</div></div>}

            <div className="mt-6 grid gap-4 lg:grid-cols-2">{(team.roles || []).map((role) => <div key={role.key} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5"><PermissionPreview role={role} /></div>)}</div>
        </section>
    );
}
