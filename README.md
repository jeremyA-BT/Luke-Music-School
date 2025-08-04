# Luke Higgins - Musician & Educator Website

A vibrant, artistic website inspired by professional musician sites like Sean Mann Guitar, featuring a striking hero section, warm color palette, and smooth animations with easy deployment controls for content.

## Quick Start

1. **Upload files** to your web server
2. **Edit contact info** in `index.html` (email and phone)
3. **Configure features** in `assets/js/script.js`
4. **Add real content** when ready

## Feature Control

Edit the `SITE_CONFIG` object in `assets/js/script.js` to show/hide sections:

```javascript
const SITE_CONFIG = {
    showGallery: false,        // Performance photos
    showAudio: false,          // Audio samples  
    showVideo: false,          // Video content
    showProfileImage: true,    // Profile photo in bio
    showTestimonials: false,   // Student testimonials
    enableContactForm: true    // Contact form functionality
};
```

## Content Sections

### Always Visible
- **Bio** - About Luke, instruments, teaching info
- **Lessons** - Course types and information
- **Contact** - Email, phone, contact form

### Optional Sections (toggleable)
- **Gallery** - Performance and teaching photos
- **Audio** - Sample recordings and performances
- **Video** - Performance videos and lesson previews  
- **Testimonials** - Student and parent feedback
- **Profile Image** - Photo in bio section

## Adding Real Content

### Replace Profile Image
1. Set `showProfileImage: true`
2. Replace `.image-placeholder` in bio section with `<img>` tag

### Add Gallery Photos
1. Set `showGallery: true`
2. Replace `.image-placeholder` divs with actual images
3. Consider adding lightbox functionality

### Add Audio Samples
1. Set `showAudio: true`
2. Replace `.audio-placeholder` with actual audio players
3. Use HTML5 `<audio>` elements or embed players

### Add Videos
1. Set `showVideo: true`
2. Replace `.video-placeholder` with video embeds
3. Use YouTube/Vimeo embeds or HTML5 `<video>`

### Update Contact Info
- Edit email and phone links in the Contact section
- Update form action if using a form processor

## Deployment Strategy

**Phase 1: Basic Launch**
```javascript
showGallery: false,
showAudio: false, 
showVideo: false,
showTestimonials: false
```

**Phase 2: Add Content Gradually**
```javascript
showProfileImage: true,
showGallery: true,
// others still false
```

**Phase 3: Full Site**
```javascript
// Set all to true as content becomes available
```

## Design Philosophy

- **Artist-First** - Inspired by Sean Mann Guitar and professional musician aesthetics
- **Vibrant & Warm** - Orange-red gradient color palette with artistic flair
- **Visual Impact** - Full-screen hero section with floating animations and parallax effects
- **Content-Rich** - Comprehensive sections with interactive placeholder content
- **Mobile Responsive** - Stunning on all devices
- **Performance Optimized** - Smooth animations without compromising speed

## Key Features

### 🎨 **Artistic Design**
- **Dramatic dark hero** with floating geometric shapes and musical patterns
- **Sophisticated gold palette** (rich gold, burgundy, slate gray, warm browns)
- **Creative typography layout** with offset names and decorative lines
- **Angular clip-path designs** throughout sections and cards
- **Complex photo framing** with corner details and layered effects
- **Asymmetrical layouts** with creative positioning and transforms

### 🎵 **Musical Elements**
- **Floating musical notes** around the hero image
- **Geometric instrument tags** with sweep animations
- **Creative lesson cards** with angled corners and accent lines
- **Pattern overlays** throughout sections
- **Artistic section dividers** with gradient lines and decorative elements
- **Dynamic hover states** with rotations and complex transforms

### ⚡ **Interactive Features**
- **Creative navigation** with text reveal effects and underline animations
- **Complex hover states** with rotations, scales, and layered transforms
- **Geometric shape animations** floating in the background
- **Staggered loading animations** for instrument tags
- **Dynamic clip-path effects** on cards and sections
- **Parallax elements** and floating musical notes

## File Structure

```
/
├── index.html          # Main page
├── assets/
│   ├── css/
│   │   └── style.css   # All styling
│   └── js/
│       └── script.js   # Functionality & config
└── README.md           # This file
```

## Customization

### Colors
Edit CSS custom properties in `style.css`:
```css
--color-primary: #d4af37;      /* Rich gold - like brass instruments */
--color-secondary: #8b4513;    /* Saddle brown - warm wood tones */
--color-accent: #2f4f4f;       /* Dark slate gray - sophisticated */
--color-warm: #cd853f;         /* Peru - warm but mature */
--color-burgundy: #722f37;     /* Deep burgundy - artistic */
--color-deep: #1e1e1e;         /* Almost black - edgy */

/* Sophisticated gradients */
--gradient-gold: linear-gradient(135deg, #d4af37 0%, #b8860b  50%, #daa520 100%);
--gradient-dark: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
```

### Typography  
Change font in CSS:
```css
--font-family: 'Inter', sans-serif;
```

### Layout
- Responsive grid system
- Mobile-first approach
- Clean section divisions

## Form Integration

The contact form is ready for integration with:
- **Formspree** - Simple form handling service
- **Netlify Forms** - If hosting on Netlify  
- **EmailJS** - Client-side email sending
- **Custom backend** - PHP, Node.js, etc.

## Performance

- Lightweight CSS and JavaScript
- Minimal external dependencies
- Optimized for shared hosting
- Fast loading times

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile browsers (iOS Safari, Chrome Mobile)
- Graceful degradation for older browsers

## License

Created for Luke Higgins - modify as needed for business use.