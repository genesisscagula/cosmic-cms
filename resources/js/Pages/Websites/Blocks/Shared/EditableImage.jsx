import {
    useState,
    useEffect,
    forwardRef,
    useImperativeHandle
} from "react";

import { createPortal } from "react-dom";
import axios from "axios";
import { showCosmicNotification } from "../../../../Components/CosmicNotification";

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

    useEffect(() => {
        setPreview(src);
    }, [src]);

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

    useImperativeHandle(ref, () => ({

        openEditor() {
            setIsEditing(true);
        }

    }));

    return (
        <>
            <div
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
                                    Choose a fresh Unsplash image or upload your own file.
                                </p>

                            </div>

                            <div className="flex-1 p-8 space-y-6 overflow-auto">

                                <div>

                                    <div className="text-xs uppercase tracking-wider text-slate-500 mb-2">
                                        File Name
                                    </div>

                                    <div className="text-white break-all">
                                        {selectedFile
                                            ? selectedFile.name
                                            : preview.split("/").pop()}
                                    </div>

                                </div>

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


                                <button
                                    type="button"
                                    onClick={handleFindRemote}
                                    disabled={findingRemote}
                                    className="w-full rounded-xl bg-emerald-600 px-4 py-3 font-semibold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {findingRemote ? 'Finding image…' : '✨ Find on Unsplash'}
                                </button>

                                <p className="text-xs leading-5 text-slate-500">
                                    Unsplash stays remote. Uploading your own image still saves it locally.
                                </p>

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
                                        setIsEditing(false);
                                    }}
                                    className="flex-1 py-3 rounded-xl bg-slate-700 hover:bg-slate-600 text-white transition"
                                >
                                    Cancel
                                </button>

                                <button
                                    disabled={!selectedFile || uploading}
                                    onClick={handleSave}
                                    className="flex-1 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold transition"
                                >
                                    {uploading
                                        ? "Uploading..."
                                        : "Save"}
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
