import {
    useState,
    useEffect,
    useRef,
    forwardRef,
    useImperativeHandle
} from "react";

import { createPortal } from "react-dom";
import axios from "axios";
import { showCosmicNotification } from "../../../../Components/CosmicNotification";
import { useCreditBalance } from "@/Hooks/useCreditBalance";

export const EditableImage = forwardRef(({
    websiteId,
    blockIndex,
    onSave,
    className,
    src,
    showOverlay = true,
    isBackground = false,
    imageQuery = '',
    blockType = ''
}, ref) => {

    const [isEditing, setIsEditing] = useState(false);
    const [selectedFile, setSelectedFile] = useState(null);
    const [preview, setPreview] = useState(src);
    const [uploading, setUploading] = useState(false);
    const [findingRemote, setFindingRemote] = useState(false);
    const [generatingAi, setGeneratingAi] = useState(false);
    const [aiPrompt, setAiPrompt] = useState(imageQuery || "");
    const [aiGeneratedUrl, setAiGeneratedUrl] = useState(null);
    const [imageDimensions, setImageDimensions] = useState({ width: 1024, height: 1024 });
    const imageSlotRef = useRef(null);
    const { balance: creditBalance, setBalance: setCreditBalance } = useCreditBalance();
    const aiImageCost = 50;

    useEffect(() => {
        setPreview(src);
        setAiGeneratedUrl(null);
    }, [src]);

    useEffect(() => {
        if (!aiPrompt && imageQuery) setAiPrompt(imageQuery);
    }, [imageQuery]);

    useEffect(() => {
        return () => {
            if (preview?.startsWith("blob:")) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleFileChange = (e) => {

        const file = e.target.files[0];

        if (!file) return;

        console.log(file);

        setSelectedFile(file);
        setPreview(URL.createObjectURL(file));

    };

    const handleSave = async () => {

        if (aiGeneratedUrl) {
            onSave(aiGeneratedUrl);
            setPreview(aiGeneratedUrl);
            setAiGeneratedUrl(null);
            setSelectedFile(null);
            setIsEditing(false);
            showCosmicNotification({
                title: "Luna image applied",
                message: "Save the page when you are ready to publish this image.",
                tone: "success",
            });
            return;
        }

        if (!selectedFile) return;

        setUploading(true);

        const formData = new FormData();

        formData.append("website_id", websiteId);
        formData.append("block_index", blockIndex);
        formData.append("image", selectedFile);

        for (const pair of formData.entries()) {
            console.log(pair[0], pair[1]);
        }

        try {

            const response = await axios.post(
                "/api/update-block-data",
                formData,
                {
                    headers: {
                        Accept: "application/json"
                    }
                }
            );

            setPreview(response.data.url);

            onSave(response.data.url);

            setSelectedFile(null);
            setIsEditing(false);

        } catch (error) {

            console.error(error);

            console.log(error.response);

            console.log(error.response?.data);

            showCosmicNotification({
                title: "Upload failed",
                message: error.response?.data?.message ?? "The image could not be uploaded. Please try again.",
                tone: "error",
            });

        } finally {

            setUploading(false);

        }

    };


    const handleFindRemote = async () => {
        if (!websiteId || findingRemote) return;

        setFindingRemote(true);

        try {
            const response = await axios.post(`/api/websites/${websiteId}/remote-image`, {
                query: imageQuery || '',
                block_type: blockType || '',
            }, { headers: { Accept: 'application/json' } });

            const url = response.data?.url;
            if (!url) throw new Error('No remote image URL returned.');

            setSelectedFile(null);
            setPreview(url);
            onSave(url);

            showCosmicNotification({
                title: 'Unsplash image ready',
                message: 'The Builder is using the remote image URL. Save the page when you are ready.',
                tone: 'success',
            });
        } catch (error) {
            console.error(error);
            showCosmicNotification({
                title: 'Unable to find an image',
                message: error.response?.data?.message ?? 'Unsplash is temporarily unavailable. Your current image was kept.',
                tone: 'error',
            });
        } finally {
            setFindingRemote(false);
        }
    };

    const resolveTargetDimensions = () => {
        const rect = imageSlotRef.current?.getBoundingClientRect?.();
        if (!rect?.width || !rect?.height) return imageDimensions;

        // Use the rendered website slot rather than the source file dimensions. This keeps
        // Luna aligned with the actual hero/card/banner crop the user is editing.
        const scale = Math.max(1, Math.min(4, 1536 / Math.max(rect.width, rect.height)));
        return {
            width: Math.max(1, Math.round(rect.width * scale)),
            height: Math.max(1, Math.round(rect.height * scale)),
        };
    };

    const handleGenerateAi = async () => {
        const prompt = aiPrompt.trim();
        if (generatingAi || prompt.length < 3) return;
        if (!websiteId) {
            showCosmicNotification({ title: 'Luna is unavailable here', message: 'This image is not connected to a website yet. Save/reload the page and try again.', tone: 'error' });
            return;
        }

        if (Number.isFinite(Number(creditBalance)) && Number(creditBalance) < aiImageCost) {
            showCosmicNotification({
                title: "Not enough Cosmic Credits",
                message: `Luna image generation costs ${aiImageCost} credits.`,
                tone: "error",
            });
            return;
        }

        setGeneratingAi(true);
        try {
            const token = new URLSearchParams(window.location.search).get('token');
            const endpoint = token
                ? `/trials/${encodeURIComponent(token)}/images/generate`
                : `/api/websites/${websiteId}/images/generate`;
            const target = resolveTargetDimensions();
            setImageDimensions(target);
            const response = await axios.post(endpoint, {
                prompt,
                image_query: imageQuery || '',
                block_type: blockType || '',
                target_width: target.width || 1024,
                target_height: target.height || 1024,
            }, { headers: { Accept: 'application/json' } });

            const url = response.data?.url;
            if (!url) throw new Error('No generated image URL returned.');

            setSelectedFile(null);
            setAiGeneratedUrl(url);
            setPreview(url);
            const nextBalance = response.data?.credit_balance ?? response.data?.balance;
            if (Number.isFinite(Number(nextBalance))) setCreditBalance(Number(nextBalance));

            showCosmicNotification({
                title: 'Luna image ready',
                message: `Generated successfully · ${aiImageCost} Cosmic Credits. Review it, then click Use Image.`,
                tone: 'success',
            });
        } catch (error) {
            console.error(error);
            showCosmicNotification({
                title: 'Luna could not generate this image',
                message: error.response?.data?.message ?? 'Generation failed. No credits were charged.',
                tone: 'error',
            });
        } finally {
            setGeneratingAi(false);
        }
    };

    useImperativeHandle(ref, () => ({

        openEditor() {
            setIsEditing(true);
        }

    }));

    return (
        <>
            <div
                ref={imageSlotRef}
                className={`${isBackground ? '' : 'relative'} group cursor-pointer ${className}`}
                onClick={() => setIsEditing(true)}
            >
                <img
                    src={preview}
                    className="
                        w-full
                        h-full
                        object-cover
                        transition
                        duration-300
                        group-hover:brightness-90
                    "
                    alt="Editable"
                />

                {showOverlay && (

                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-center justify-center">

                    <div className="opacity-0 group-hover:opacity-100 transition text-white text-sm font-medium bg-black/60 px-4 py-2 rounded-full backdrop-blur">
                        📷 Edit Image
                    </div>

                </div>

                )}
            </div>

            {isEditing &&
                createPortal(

                <div
                    className="
                        cosmic-media-manager-overlay
                        fixed
                        inset-0
                        z-[999999]
                        bg-slate-950/80
                        backdrop-blur-xl

                        flex
                        items-center
                        justify-center

                        p-8
                    "
                    onClick={() => {
                        setPreview(src);
                        setSelectedFile(null);
                        setAiGeneratedUrl(null);
                        setIsEditing(false);
                    }}
                >

                    <div
                        className="cosmic-media-manager-modal w-full
                        max-w-[1700px]

                        h-[90vh]
                        max-h-[1000px] 
                        rounded-3xl overflow-hidden bg-slate-900 border border-slate-700 shadow-2xl flex"
                        onClick={(e) => e.stopPropagation()}
                    >

                        {/* LEFT */}

                        <div className="cosmic-media-manager-preview flex-1 bg-slate-950 flex items-center justify-center p-10">

                            <img
                                src={preview}
                                className="max-w-full max-h-full rounded-2xl shadow-2xl object-contain"

                            />

                        </div>

                        {/* RIGHT */}

                        <div className="cosmic-media-manager-panel w-[380px] bg-slate-900 border-l border-slate-700 flex flex-col">

                            <div className="p-8 border-b border-slate-700">

                                <h2 className="text-2xl font-bold text-white">
                                    Media Manager
                                </h2>

                                <p className="text-slate-400 text-sm mt-2">
                                    Generate a custom image with Cosmic AI or upload your own file.
                                </p>

                            </div>

                            <div className="flex-1 p-8 space-y-6 overflow-auto">

                                {selectedFile && (

                                    <>
                                        <div>

                                            <div className="text-xs uppercase tracking-wider text-slate-500 mb-2">
                                                File Size
                                            </div>

                                            <div className="text-white">
                                                {(selectedFile.size / 1024).toFixed(1)} KB
                                            </div>

                                        </div>

                                        <div>

                                            <div className="text-xs uppercase tracking-wider text-slate-500 mb-2">
                                                File Type
                                            </div>

                                            <div className="text-white">
                                                {selectedFile.type}
                                            </div>

                                        </div>
                                    </>

                                )}


                                <div className="rounded-2xl border border-violet-400/20 bg-violet-500/[0.08] p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">Cosmic · AI Image</div>
                                            <div className="mt-1 text-xs text-slate-400">Auto-fits the current image slot · {aiImageCost} credits per successful generation.</div>
                                        </div>
                                        <span className="shrink-0 rounded-full bg-amber-400/10 px-2.5 py-1 text-[11px] font-bold text-amber-300">⚡ {aiImageCost}</span>
                                    </div>
                                    <textarea
                                        rows={4}
                                        value={aiPrompt}
                                        onChange={(event) => setAiPrompt(event.target.value)}
                                        placeholder="Describe the image you want Luna to create…"
                                        className="mt-3 w-full resize-none rounded-xl border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm leading-5 text-white outline-none placeholder:text-slate-600 focus:border-violet-400/50"
                                    />
                                    <div className="mt-2 flex items-center justify-between text-[11px] text-slate-500">
                                        <span>Target: {imageDimensions.width} × {imageDimensions.height}</span>
                                        <span>Balance: {Number.isFinite(Number(creditBalance)) ? Number(creditBalance) : '—'}</span>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleGenerateAi}
                                        disabled={generatingAi || aiPrompt.trim().length < 3}
                                        className="mt-3 w-full rounded-xl bg-violet-500 px-4 py-3 font-bold text-white transition hover:bg-violet-400 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {generatingAi ? 'Luna is creating…' : `✦ Generate with Luna · ${aiImageCost} Credits`}
                                    </button>
                                    {aiGeneratedUrl ? <p className="mt-2 text-xs font-semibold text-emerald-300">✓ AI image generated. Click Use Image below to apply it.</p> : null}
                                </div>

                                <label className="block">

                                    <input
                                        id={`hero-bg-${blockIndex}`}
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        onChange={handleFileChange}
                                    />

                                    <div className="cursor-pointer rounded-xl border-2 border-dashed border-slate-600 hover:border-blue-500 transition p-8 text-center">

                                        <div className="text-4xl mb-4">
                                            🖼️
                                        </div>

                                        <div className="text-white font-semibold">
                                            Choose Image
                                        </div>

                                        <div className="text-slate-400 text-sm mt-2">
                                            JPG, PNG, WEBP, AVIF
                                        </div>

                                    </div>

                                </label>

                            </div>

                            <div className="p-8 border-t border-slate-700 flex gap-3">

                                <button
                                    onClick={() => {
                                        setPreview(src);
                                        setSelectedFile(null);
                                        setAiGeneratedUrl(null);
                                        setIsEditing(false);
                                    }}
                                    className="flex-1 py-3 rounded-xl bg-slate-700 hover:bg-slate-600 text-white transition"
                                >
                                    Cancel
                                </button>

                                <button
                                    disabled={(!selectedFile && !aiGeneratedUrl) || uploading}
                                    onClick={handleSave}
                                    className="flex-1 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold transition"
                                >
                                    {uploading
                                        ? "Uploading..."
                                        : (aiGeneratedUrl ? "Use Image" : "Save")}
                                </button>

                            </div>

                        </div>

                    </div>

                </div>,

                document.body

            )}
        </>
    );

});
