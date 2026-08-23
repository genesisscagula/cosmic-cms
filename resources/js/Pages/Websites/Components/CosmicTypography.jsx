import React from "react";

const merge = (...values) => values.filter(Boolean).join(" ");

function Type({
    as: Tag = "span",
    role,
    className = "",
    children,
    lunaTarget,
    lunaDisplay = "text",
    ...props
}) {
    return (
        <Tag
            data-cosmic-type={role}
            {...(lunaTarget ? {
                "data-cosmic-luna-display": lunaDisplay,
                "data-luna-target": lunaTarget,
            } : {})}
            className={merge(`cosmic-type-${role}`, className)}
            {...props}
        >
            {children}
        </Tag>
    );
}

export const CosmicH1 = (props) => <Type as="h1" role="h1" lunaTarget="heading" {...props} />;
export const CosmicH2 = (props) => <Type as="h2" role="h2" lunaTarget="heading" {...props} />;
export const CosmicH3 = (props) => <Type as="h3" role="h3" lunaTarget="heading" {...props} />;
export const CosmicH4 = (props) => <Type as="h4" role="h4" lunaTarget="heading" {...props} />;
export const CosmicH5 = (props) => <Type as="h5" role="h5" lunaTarget="heading" {...props} />;
export const CosmicH6 = (props) => <Type as="h6" role="h6" lunaTarget="heading" {...props} />;
export const CosmicLead = (props) => <Type role="lead" lunaTarget="text" {...props} />;
export const CosmicBody = (props) => <Type role="body" lunaTarget="text" {...props} />;
export const CosmicEyebrow = (props) => <Type role="eyebrow" lunaTarget="label" {...props} />;
export const CosmicSmall = (props) => <Type role="small" lunaTarget="label" {...props} />;
export const CosmicBadge = (props) => <Type role="badge" lunaTarget="text" {...props} />;
export const CosmicMeta = (props) => <Type role="meta" lunaTarget="label" {...props} />;
export const CosmicStatTitle = (props) => <Type role="stat-title" lunaTarget="heading" {...props} />;
export const CosmicCardTitle = (props) => <Type role="card-title" lunaTarget="heading" {...props} />;
export const CosmicCardBody = (props) => <Type role="card-body" lunaTarget="text" {...props} />;
export const CosmicButtonText = (props) => <Type role="button" lunaTarget="button" lunaDisplay="button" {...props} />;

/**
 * Maps website-wide typography settings to CSS variables. Batch 3 can feed
 * Luna's global requests into this object. A Spark/section can instead set
 * --cosmic-local-* variables to override only itself.
 */
export const cosmicTypographyVars = (settings = {}) => {
    const roles = ["h1","h2","h3","h4","h5","h6","card-title","stat-title","card-body","lead","body","eyebrow","small","badge","meta","button"];
    const fields = ["size","line","weight","tracking","color"];
    const vars = {};

    if (settings.font_display) vars["--cosmic-font-display"] = String(settings.font_display);
    if (settings.font_body) vars["--cosmic-font-body"] = String(settings.font_body);
    if (settings.heading_color) vars["--cosmic-color-heading"] = String(settings.heading_color);
    if (settings.body_color) vars["--cosmic-color-body"] = String(settings.body_color);
    if (settings.eyebrow_color) vars["--cosmic-color-eyebrow"] = String(settings.eyebrow_color);

    roles.forEach((role) => {
        fields.forEach((field) => {
            const key = `${role}_${field}`;
            if (settings[key] !== undefined && settings[key] !== null && settings[key] !== "") {
                vars[`--cosmic-type-${role}-${field}`] = String(settings[key]);
            }
        });
        ["tablet","mobile"].forEach((breakpoint) => {
            const key = `${role}_size_${breakpoint}`;
            if (settings[key] !== undefined && settings[key] !== null && settings[key] !== "") {
                vars[`--cosmic-type-${role}-size-${breakpoint}`] = String(settings[key]);
            }
        });
    });

    return vars;
};

export const cosmicLocalTypographyVars = (settings = {}) => {
    const vars = {};
    Object.entries(cosmicTypographyVars(settings)).forEach(([key,value]) => {
        if (key === "--cosmic-font-display") vars["--cosmic-local-font-display"] = value;
        else if (key === "--cosmic-font-body") vars["--cosmic-local-font-body"] = value;
        else vars[key.replace("--cosmic-type-", "--cosmic-local-")] = value;
    });
    return vars;
};
