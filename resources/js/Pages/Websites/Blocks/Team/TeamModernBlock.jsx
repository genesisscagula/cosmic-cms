import { usePage } from "@inertiajs/react";
import { EditableImage } from "../Shared/EditableImage";
import { EditableText } from "../Shared/EditableText";
import { getEffectiveTheme } from "../../../../theme/Theme";
import { RepeatableControls, RepeatableRemoveButton, removeAt } from "../Shared/RepeatableControls";
import { sparkTw } from "../Shared/sparkTailwindRuntime";

const TEAM_MEMBER_PRESETS = [
    {
        name: "Alex Morgan",
        role: "Founder & Director",
        bio: "Leads the team with a practical, client-first approach, turning business goals into clear priorities and dependable project outcomes.",
        image_url: "/storage/cms-images/avatars/avatar-1.jpg",
    },
    {
        name: "Jordan Lee",
        role: "Client Experience Lead",
        bio: "Keeps every project organized, responsive, and easy to navigate while making sure clients feel informed and supported throughout the process.",
        image_url: "/storage/cms-images/avatars/avatar-2.jpg",
    },
    {
        name: "Taylor Brooks",
        role: "Creative Lead",
        bio: "Transforms early ideas into polished digital experiences that communicate clearly, feel intuitive, and support meaningful customer engagement.",
        image_url: "/storage/cms-images/avatars/avatar-3.jpg",
    },
    {
        name: "Casey Rivera",
        role: "Operations Manager",
        bio: "Coordinates people, timelines, and internal processes to maintain consistent quality and keep every project moving smoothly from start to finish.",
        image_url: "/storage/cms-images/avatars/avatar-4.jpg",
    },
    {
        name: "Morgan Chen",
        role: "Strategy Lead",
        bio: "Connects business goals with practical digital strategies, helping clients identify opportunities and build a clear plan for sustainable growth.",
        image_url: "/storage/cms-images/avatars/avatar-5.jpg",
    },
    {
        name: "Riley Patel",
        role: "Project Manager",
        bio: "Manages schedules, communication, and project details to ensure each stage stays focused, coordinated, and aligned with the agreed objectives.",
        image_url: "/storage/cms-images/avatars/avatar-6.jpg",
    },
    {
        name: "Avery Stone",
        role: "Design Lead",
        bio: "Shapes thoughtful digital experiences that balance visual quality, usability, and clarity across every screen and customer touchpoint.",
        image_url: "/storage/cms-images/avatars/avatar-1.jpg",
    },
    {
        name: "Cameron Wright",
        role: "Client Partner",
        bio: "Builds strong client relationships through clear communication, practical guidance, and dependable support during every stage of delivery.",
        image_url: "/storage/cms-images/avatars/avatar-2.jpg",
    },
    {
        name: "Jamie Kim",
        role: "Content Lead",
        bio: "Creates structured, engaging content that explains complex ideas clearly and helps customers understand the value behind each service.",
        image_url: "/storage/cms-images/avatars/avatar-3.jpg",
    },
    {
        name: "Drew Bennett",
        role: "Delivery Lead",
        bio: "Oversees final reviews, quality checks, and project handovers to make sure every finished product is reliable, polished, and ready to perform.",
        image_url: "/storage/cms-images/avatars/avatar-4.jpg",
    },
    {
        name: "Quinn Harper",
        role: "Community Lead",
        bio: "Strengthens customer relationships by listening closely, sharing useful insights, and creating positive experiences around the brand and its services.",
        image_url: "/storage/cms-images/avatars/avatar-5.jpg",
    },
    {
        name: "Reese Alvarez",
        role: "Operations Coordinator",
        bio: "Supports the team with reliable systems, careful coordination, and consistent follow-through across daily tasks and ongoing client projects.",
        image_url: "/storage/cms-images/avatars/avatar-6.jpg",
    },
];

export const TeamModernSchema = {
    type: "team_modern",
    title: "Team Modern",
    category: "Team",
    purpose: "Introduce the people behind a business with a refined, editable team grid.",
    description: "Display a concise team introduction and four professional member profiles.",
    tags: ["team", "people", "leadership", "staff", "about"],
    defaults: {
        eyebrow: "Meet the team",
        heading: "The people behind the work",
        text: "A dedicated team focused on thoughtful service, clear communication, and dependable results.",
        members: TEAM_MEMBER_PRESETS.slice(0, 4),
    },
    fields: [
        { key: "eyebrow", type: "text", label: "Eyebrow" },
        { key: "heading", type: "text", label: "Heading" },
        { key: "text", type: "textarea", label: "Supporting text" },
        {
            key: "members",
            type: "repeater",
            label: "Team members",
            fields: [
                { key: "image_url", type: "image", label: "Photo" },
                { key: "name", type: "text", label: "Name" },
                { key: "role", type: "text", label: "Role" },
                { key: "bio", type: "textarea", label: "Bio" },
            ],
        },
    ],
};

export function TeamModernBlock({ block, blockIndex, onUpdate, globalTheme }) {
    const requestedTheme = block.theme && block.theme !== "auto" ? block.theme : block.resolvedTheme;
    const theme = getEffectiveTheme(requestedTheme, globalTheme);
    const { website } = usePage().props;
    const data = {
        ...TeamModernSchema.defaults,
        ...block,
        members: Array.isArray(block.members) && block.members.length
            ? block.members.slice(0, 8)
            : TeamModernSchema.defaults.members,
    };

    const updateMember = (index, field, value) => {
        onUpdate({
            members: data.members.map((member, memberIndex) => (
                memberIndex === index ? { ...member, [field]: value } : member
            )),
        });
    };

    const addMember = () => {
        const preset = TEAM_MEMBER_PRESETS[data.members.length % TEAM_MEMBER_PRESETS.length];

        onUpdate({
            members: [...data.members, { ...preset }],
        });
    };

    const removeMember = (index) => {
        if (data.members.length <= 1) {
            return;
        }

        onUpdate({
            members: data.members.filter((_, memberIndex) => memberIndex !== index),
        });
    };

    return (
        <section className={sparkTw(block, "auto_1", `group/repeatable-section px-6 py-16 sm:px-8 lg:py-20 ${theme.bg} transition-colors duration-500`)}>
            <div className={sparkTw(block, "auto_2", "mx-auto max-w-7xl")}>
                <div className={sparkTw(block, "auto_3", "mb-10 max-w-2xl space-y-4 sm:mb-12")}>
                    {data.eyebrow && (
                        <EditableText
                            value={data.eyebrow}
                            className={sparkTw(block, "auto_4", `block text-xs font-semibold uppercase tracking-[0.22em] ${theme.sub}`)}
                            onSave={(eyebrow) => onUpdate({ eyebrow })}
                        />
                    )}
                    <EditableText
                        value={data.heading} cosmicType="h2"
                        className={sparkTw(block, "auto_5", `block text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl lg:text-[3.75rem] ${theme.text}`)}
                        onSave={(heading) => onUpdate({ heading })}
                    />
                    {data.text && (
                        <EditableText
                            value={data.text}
                            isTextArea
                            className={sparkTw(block, "auto_6", `block max-w-xl text-base leading-7 ${theme.sub}`)}
                            onSave={(text) => onUpdate({ text })}
                        />
                    )}
                </div>

                <div className={sparkTw(block, "auto_7", "grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4")}>
                    {data.members.map((member, index) => (
                        <article
                            key={index}
                            className={sparkTw(block, "auto_8", `group relative overflow-hidden rounded-2xl border ${theme.border} ${theme.card}`)}
                        >
                            <EditableImage
                                websiteId={website?.id}
                                blockIndex={blockIndex}
                                src={member.image_url || TEAM_MEMBER_PRESETS[index % TEAM_MEMBER_PRESETS.length].image_url}
                                className={sparkTw(block, "auto_9", "aspect-[4/3] w-full")}
                                onSave={(image_url) => updateMember(index, "image_url", image_url)}
                            />
                            <div className={sparkTw(block, "auto_10", "space-y-2 p-5")}>
                                <EditableText
                                    value={member.name}
                                    className={sparkTw(block, "auto_11", `block text-base font-semibold ${theme.text}`)}
                                    onSave={(name) => updateMember(index, "name", name)}
                                />
                                <EditableText
                                    value={member.role}
                                    className={sparkTw(block, "auto_12", `block text-sm font-medium ${theme.sub}`)}
                                    onSave={(role) => updateMember(index, "role", role)}
                                />
                                {member.bio && (
                                    <EditableText
                                        value={member.bio}
                                        isTextArea
                                        className={sparkTw(block, "auto_13", `block pt-1 text-sm leading-6 ${theme.sub}`)}
                                        onSave={(bio) => updateMember(index, "bio", bio)}
                                    />
                                )}
                                <RepeatableRemoveButton hoverScope="card"
                                    onRemove={() => removeMember(index)}
                                    disabled={data.members.length <= 1}
                                    label="Remove member"
                                    overlay
                                />
                            </div>
                        </article>
                    ))}
                </div>
                <RepeatableControls
                    onAdd={() => data.members.length < 8 && addMember()}
                    onRemove={() => onUpdate({ members: removeAt(data.members, data.members.length - 1, 1) })}
                    canAdd={data.members.length < 8}
                    addLabel="Add team member"
                    showRemove={false}
                />
            </div>
        </section>
    );
}
