export function getCosmicAvatar(index = 0) {
    const avatarNumber = (Number(index) % 6) + 1;
    return `/storage/cms-images/avatars/avatar-${avatarNumber}.jpg`;
}

export function resolveTestimonialAvatar(item = {}, index = 0) {
    return item.avatar || getCosmicAvatar(index);
}
