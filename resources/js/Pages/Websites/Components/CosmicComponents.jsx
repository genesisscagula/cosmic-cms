import React from "react";

const cx = (...values) => values.filter(Boolean).join(" ");

export function CosmicButton({ as: Tag="a", variant="primary", className="", children, ...props }) {
    return <Tag data-cosmic-button={variant} data-cosmic-radius="button" className={cx("cosmic-button", `cosmic-button--${variant}`, className)} {...props}>{children}</Tag>;
}

export function CosmicCard({ as: Tag="article", className="", children, shadow=true, ...props }) {
    return <Tag data-cosmic-card="1" data-cosmic-radius="card" {...(shadow?{"data-cosmic-shadow":"card"}:{})} className={cx("cosmic-card", className)} {...props}>{children}</Tag>;
}

export function CosmicImage({ className="", aspect="auto", ...props }) {
    return <img data-cosmic-media={aspect} data-cosmic-radius="image" className={cx("cosmic-media", className)} {...props}/>;
}

export function CosmicInput({ className="", ...props }) {
    return <input data-cosmic-form-field="1" data-cosmic-radius="input" className={cx("cosmic-form-field", className)} {...props}/>;
}

export function CosmicTextarea({ className="", ...props }) {
    return <textarea data-cosmic-form-field="1" data-cosmic-radius="input" className={cx("cosmic-form-field", className)} {...props}/>;
}

export function CosmicSelect({ className="", children, ...props }) {
    return <select data-cosmic-form-field="1" data-cosmic-radius="input" className={cx("cosmic-form-field", className)} {...props}>{children}</select>;
}

export function CosmicLabel({ className="", children, ...props }) {
    return <label data-cosmic-form-label="1" className={cx("cosmic-form-label", className)} {...props}>{children}</label>;
}

export function CosmicLink({ as: Tag="a", className="", children, ...props }) {
    return <Tag data-cosmic-link="1" className={cx("cosmic-link", className)} {...props}>{children}</Tag>;
}
