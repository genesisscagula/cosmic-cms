import { useState } from "react";

import { getEffectiveTheme } from "../../../../theme/Theme";
import { colorFamilies } from "../../../../theme/colorFamilies";


export function EditableImage({ websiteId, blockIndex, onSave, className, src }) {
    const [isEditing, setIsEditing] = useState(false);
    const [selectedFile, setSelectedFile] = useState(null);
    const [preview, setPreview] = useState(src);
    const [uploading, setUploading] = useState(false);

    useEffect(() => {
        return () => {
            if (preview && preview.startsWith('blob:')) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            setSelectedFile(file);
            setPreview(URL.createObjectURL(file));
        }
    };

    const handleSave = async () => {
        if (!selectedFile) return;
        setUploading(true);

        const formData = new FormData();
        formData.append('website_id', websiteId);
        formData.append('block_index', blockIndex);
        formData.append('image', selectedFile);

        try {
            const response = await axios.post('/api/update-block-data', formData);
            onSave(response.data.url); 
            setIsEditing(false);
            setSelectedFile(null);
        } catch (error) {
            console.error("Error saving:", error);
            alert('Failed to save to server.');
        } finally {
            setUploading(false);
        }
    };

    return (
        <div className="relative group cursor-pointer" onClick={() => setIsEditing(true)}>
            <img src={preview} className={className} alt="Editable" />
            
            {isEditing && (
                <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999] p-4">
                    <div className="bg-slate-900 p-6 rounded-2xl w-full max-w-sm pointer-events-auto z-[10000] relative" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-white font-bold mb-4">Media Manager</h3>
                        <img src={preview} className="w-full h-32 object-cover rounded-lg mb-4" />
                        
                        <div className="flex gap-2">
                            <label className="flex-1 bg-blue-600 py-2 rounded-lg text-white text-center cursor-pointer hover:bg-blue-700">
                                Select Image
                                <input type="file" className="hidden" onChange={handleFileChange} />
                            </label>
                            
                            {selectedFile && (
                                <button 
                                    type="button"
                                    onClick={handleSave} 
                                    className="flex-1 bg-emerald-600 py-2 rounded-lg text-white font-bold hover:bg-emerald-700"
                                >
                                    {uploading ? 'Saving...' : 'Save Changes'}
                                </button>
                            )}
                            
                            <button 
                                type="button"
                                onClick={(e) => { 
                                    e.stopPropagation();
                                    setPreview(src);
                                    setSelectedFile(null);
                                    setIsEditing(false);
                                }} 
                                className="px-4 bg-slate-800 rounded-lg text-white hover:bg-slate-700"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}