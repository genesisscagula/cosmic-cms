import { forwardRef, useImperativeHandle } from "react";
export const EditableImageGallery = forwardRef(function EditableImageGallery(_, ref) {
    useImperativeHandle(ref, () => ({ openEditor() {} }), []);
    return null;
});
