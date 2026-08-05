import { usePage } from "@inertiajs/react";

export default function usePlanEntitlements() {
    const { auth = {}, cosmicPlans = {} } = usePage().props;
    const plan = auth.plan || null;
    const capabilities = plan?.capabilities || {};
    const personal = plan?.personal_entitlements || null;
    const agency = plan?.agency_entitlements || null;

    const value = (key, fallback = null) => capabilities[key] ?? fallback;
    const allows = (key, expected = true) => value(key) === expected;

    return {
        plan,
        plans: cosmicPlans,
        capabilities,
        personal,
        agency,
        personalLimits: personal?.limits || {},
        personalAccess: personal?.access || {},
        personalFeatures: personal?.features || {},
        agencyLimits: agency?.limits || {},
        agencyAccess: agency?.access || {},
        agencyFeatures: agency?.features || {},
        value,
        allows,
        allowsPersonalFeature: (key) => personal?.features?.[key] === true,
        allowsAgencyFeature: (key) => agency?.features?.[key] === true,
        isPersonal: plan?.is_personal === true,
        isAgency: plan?.is_agency === true,
        canAddSites: plan?.can_add_sites !== false,
        upgradeRequired: plan?.upgrade_required === true,
    };
}
