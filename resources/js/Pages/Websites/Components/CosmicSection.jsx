import React from "react";

const classes = (...values) => values.filter(Boolean).join(" ");

export const cosmicSectionVars = (settings = {}) => {
    const map = {
        py: "--cosmic-section-py",
        py_tablet: "--cosmic-section-py-tablet",
        py_mobile: "--cosmic-section-py-mobile",
        px: "--cosmic-section-px",
        px_tablet: "--cosmic-section-px-tablet",
        px_mobile: "--cosmic-section-px-mobile",
        container: "--cosmic-section-container",
        container_wide: "--cosmic-section-container-wide",
        container_narrow: "--cosmic-section-container-narrow",
        gap: "--cosmic-section-gap",
        gap_tablet: "--cosmic-section-gap-tablet",
        gap_mobile: "--cosmic-section-gap-mobile",
        min_height: "--cosmic-section-min-height",
    };
    return Object.fromEntries(
        Object.entries(map)
            .filter(([key]) => settings[key] !== undefined && settings[key] !== null && settings[key] !== "")
            .map(([key, variable]) => [variable, String(settings[key])])
    );
};

export const cosmicLocalSectionVars = (settings = {}) => {
    const map = {
        py: "--cosmic-local-section-py",
        py_top: "--cosmic-local-section-py-top",
        py_bottom: "--cosmic-local-section-py-bottom",
        px: "--cosmic-local-section-px",
        container: "--cosmic-local-section-container",
        gap: "--cosmic-local-section-gap",
        min_height: "--cosmic-local-section-min-height",
    };
    return Object.fromEntries(
        Object.entries(map)
            .filter(([key]) => settings[key] !== undefined && settings[key] !== null && settings[key] !== "")
            .map(([key, variable]) => [variable, String(settings[key])])
    );
};

export function CosmicSection({
    as: Tag = "section",
    className = "",
    containerClassName = "",
    container = "default",
    children,
    style,
    containerStyle,
    ...props
}) {
    const containerClass = container === "wide"
        ? "cosmic-section-container cosmic-section-container--wide"
        : container === "narrow"
            ? "cosmic-section-container cosmic-section-container--narrow"
            : "cosmic-section-container";

    return (
        <Tag
            data-cosmic-section-wrapper="1"
            className={classes("cosmic-section", className)}
            style={style}
            {...props}
        >
            <div
                data-cosmic-section-container="1"
                className={classes(containerClass, containerClassName)}
                style={containerStyle}
            >
                {children}
            </div>
        </Tag>
    );
}

export function CosmicSectionStack({ as: Tag = "div", className = "", children, ...props }) {
    return <Tag className={classes("cosmic-section-stack", className)} {...props}>{children}</Tag>;
}
