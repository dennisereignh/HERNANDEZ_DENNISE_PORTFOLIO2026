/* Shared icon set (same icons your website already uses). Used by the admin and the public pages. */
(function () {
  var attrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
  function svg(inner) { return '<svg ' + attrs + '>' + inner + '</svg>'; }
  window.PORTFOLIO_ICONS = {
    globe:    { label: 'Globe (web)',        svg: svg('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>') },
    layout:   { label: 'Layout (Bootstrap)', svg: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M9 9v11"/>') },
    terminal: { label: 'Terminal (Python)',  svg: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 10 3 2-3 2M12 15h5"/>') },
    palette:  { label: 'Palette (design)',   svg: svg('<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-1 1.6-2.1-.4-1.1.3-2.4 1.6-2.4H17a4 4 0 0 0 4-4A9 9 0 0 0 12 3z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>') },
    pen:      { label: 'Pen (art)',          svg: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>') },
    network:  { label: 'Network',            svg: svg('<rect x="9" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/><path d="M12 9v3M6 15v-3h12v3"/>') },
    database: { label: 'Database',           svg: svg('<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>') },
    video:    { label: 'Video',              svg: svg('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 9.5v5l4.5-2.5z"/>') },
    code:     { label: 'Code',               svg: svg('<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 5l-4 14"/>') },
    java:     { label: 'Cup (Java)',         svg: svg('<path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5zM16 10h2a2 2 0 0 1 0 4h-2M7 3v2M11 3v2"/>') },
    layers:   { label: 'Layers (Photoshop)', svg: svg('<path d="m12 3 9 5-9 5-9-5zM3 13l9 5 9-5"/>') },
    folder:   { label: 'Folder (other)',     svg: svg('<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>') }
  };
})();
