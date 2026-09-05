/**
 * Initials for the avatar fallback. Registered as an Alpine magic so rows
 * rendered by x-for can reuse <x-avatar> instead of copying its markup
 * (context.md §3.1).
 *
 * Mirrors the PHP branch in avatar.blade.php: first + last word, two letters.
 */
export default (name) => {
    const words = String(name ?? '').trim().split(/\s+/).filter(Boolean)

    if (words.length === 0) return '?'
    if (words.length === 1) return words[0].slice(0, 2).toUpperCase()

    return (words[0][0] + words[words.length - 1][0]).toUpperCase()
}
