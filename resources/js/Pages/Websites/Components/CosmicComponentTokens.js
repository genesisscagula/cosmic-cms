export const cosmicComponentVars = (settings = {}) => {
    const map = {
        button_height: "--cosmic-button-height",
        button_height_sm: "--cosmic-button-height-sm",
        button_height_lg: "--cosmic-button-height-lg",
        button_px: "--cosmic-button-px",
        button_height_tablet: "--cosmic-button-height-tablet",
        button_height_mobile: "--cosmic-button-height-mobile",
        button_px_tablet: "--cosmic-button-px-tablet",
        button_px_mobile: "--cosmic-button-px-mobile",
        button_radius: "--cosmic-radius-button",
        button_weight: "--cosmic-button-font-weight",
        button_hover_shift: "--cosmic-button-hover-shift",
        button_primary_bg: "--cosmic-button-primary-bg",
        button_primary_text: "--cosmic-button-primary-text",
        button_secondary_bg: "--cosmic-button-secondary-bg",
        button_secondary_text: "--cosmic-button-secondary-text",
        input_height: "--cosmic-input-height",
        input_height_tablet: "--cosmic-input-height-tablet",
        input_height_mobile: "--cosmic-input-height-mobile",
        input_px: "--cosmic-input-px",
        input_radius: "--cosmic-radius-input",
        input_border: "--cosmic-input-border",
        input_focus: "--cosmic-input-focus",
        card_radius: "--cosmic-radius-card",
        card_padding: "--cosmic-card-padding",
        card_padding_tablet: "--cosmic-card-padding-tablet",
        card_padding_mobile: "--cosmic-card-padding-mobile",
        card_shadow: "--cosmic-card-shadow",
        card_bg: "--cosmic-card-bg",
        card_border: "--cosmic-card-border",
        image_radius: "--cosmic-radius-image",
        media_object_fit: "--cosmic-media-object-fit",
        link_color: "--cosmic-link-color",
        link_hover: "--cosmic-link-hover",
        link_decoration: "--cosmic-link-decoration",
        modal_radius: "--cosmic-radius-modal",
        section_radius: "--cosmic-radius-section",
    };
    return Object.fromEntries(
        Object.entries(map)
            .filter(([key]) => settings[key] !== undefined && settings[key] !== null && settings[key] !== "")
            .map(([key, variable]) => [variable, String(settings[key])])
    );
};

export const cosmicLocalComponentVars = (settings = {}) => {
    const map = {
        button_height: "--cosmic-local-button-height",
        button_px: "--cosmic-local-button-px",
        button_radius: "--cosmic-local-button-radius",
        button_weight: "--cosmic-local-button-weight",
        button_bg: "--cosmic-local-button-bg",
        button_text: "--cosmic-local-button-text",
        button_border: "--cosmic-local-button-border",
        input_height: "--cosmic-local-input-height",
        input_px: "--cosmic-local-input-px",
        input_radius: "--cosmic-local-input-radius",
        input_bg: "--cosmic-local-input-bg",
        input_text: "--cosmic-local-input-text",
        input_border: "--cosmic-local-input-border",
        input_focus: "--cosmic-local-input-focus",
        card_radius: "--cosmic-local-card-radius",
        card_padding: "--cosmic-local-card-padding",
        card_shadow: "--cosmic-local-card-shadow",
        card_bg: "--cosmic-local-card-bg",
        card_text: "--cosmic-local-card-text",
        card_heading: "--cosmic-local-card-heading",
        card_border: "--cosmic-local-card-border",
        image_radius: "--cosmic-local-image-radius",
        media_object_fit: "--cosmic-local-media-object-fit",
        link_color: "--cosmic-local-link-color",
        link_hover: "--cosmic-local-link-hover",
        link_decoration: "--cosmic-local-link-decoration",
        modal_radius: "--cosmic-local-modal-radius",
        section_radius: "--cosmic-local-section-radius",
    };
    return Object.fromEntries(
        Object.entries(map)
            .filter(([key]) => settings[key] !== undefined && settings[key] !== null && settings[key] !== "")
            .map(([key, variable]) => [variable, String(settings[key])])
    );
};
