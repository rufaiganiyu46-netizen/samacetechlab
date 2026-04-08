<style>
    body.principal-page {
        background: #0A0A0F !important;
        color: #fff;
    }

    body.principal-page .orb,
    body.principal-page #particles-container,
    body.principal-page .shared-bg-orb,
    body.principal-page .shared-background-canvas,
    body.principal-page #shared-background-canvas,
    body.principal-page #backgroundCanvas,
    body.principal-page .ui-ripple,
    body.principal-page .ui-reactive::before,
    body.principal-page .ui-glass-surface::before,
    body.principal-page .ui-glass-surface::after {
        display: none !important;
        opacity: 0 !important;
    }

    body.principal-page .ui-float,
    body.principal-page .ui-motion-stage,
    body.principal-page .ui-reactive,
    body.principal-page .ui-reveal,
    body.principal-page .ui-reveal.is-visible {
        animation: none !important;
        transform: none !important;
        transition: none !important;
    }

    body.principal-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        height: 100vh;
        background: rgba(13,13,24,0.95) !important;
        border-right: 1px solid rgba(255,255,255,0.06) !important;
        display: flex;
        flex-direction: column;
        z-index: 1100;
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        overflow-y: auto;
        transition: transform .25s ease;
        padding: 0;
        color: #fff;
    }

    body.principal-page .sidebar-logo {
        padding: 28px 24px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
        position: relative;
    }

    body.principal-page .sidebar-logo::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 24px;
        right: 24px;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(245,166,35,0.3), transparent);
    }

    body.principal-page .sidebar-school-name {
        font-family: 'Bebas Neue', cursive;
        font-size: 17px;
        letter-spacing: 2.5px;
        color: #F5A623;
        display: block;
        line-height: 1;
    }

    body.principal-page .sidebar-school-sub {
        font-size: 9px;
        color: #5A6070;
        letter-spacing: 2px;
        text-transform: uppercase;
        display: block;
        margin-top: 4px;
    }

    body.principal-page .sidebar-role-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(245,166,35,0.1);
        border: 1px solid rgba(245,166,35,0.2);
        color: #F5A623;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 1px;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 50px;
        margin-top: 10px;
    }

    body.principal-page .nav-section-label {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #5A6070;
        padding: 20px 24px 8px;
    }

    body.principal-page .sidebar nav {
        padding: 8px 0;
        flex: 1;
    }

    body.principal-page .sidebar nav a {
        display: flex;
        align-items: center;
        gap: 12px;
        width: calc(100% - 12px);
        margin-right: 12px;
        padding: 13px 24px;
        color: #A0A8B8;
        font-size: 14px;
        font-weight: 500;
        border-left: 3px solid transparent;
        transition: color .2s ease, background .2s ease, border-color .2s ease;
        position: relative;
        border-radius: 0 12px 12px 0;
        background: transparent;
        text-decoration: none;
    }

    body.principal-page .nav-icon {
        font-size: 18px;
        width: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    body.principal-page .nav-label { flex: 1; }

    body.principal-page .nav-badge {
        background: #FF4757;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        min-width: 18px;
        height: 18px;
        border-radius: 50px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.principal-page .sidebar nav a:hover {
        color: #fff;
        background: rgba(255,255,255,0.05);
        border-left-color: rgba(245,166,35,0.4);
        transform: none;
    }

    body.principal-page .sidebar nav a.active {
        color: #F5A623;
        background: rgba(245,166,35,0.08);
        border-left-color: #F5A623;
        font-weight: 600;
    }

    body.principal-page .sidebar nav a.active::before {
        content: '';
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #F5A623;
        box-shadow: 0 0 8px rgba(245,166,35,0.8);
    }

    body.principal-page .logout-link {
        color: #FF4757 !important;
        margin-top: 4px;
    }

    body.principal-page .logout-link:hover {
        background: rgba(255,71,87,0.08) !important;
        border-left-color: #FF4757 !important;
        color: #FF4757 !important;
    }

    body.principal-page .sidebar-footer {
        padding: 16px 24px 24px;
        border-top: 1px solid rgba(255,255,255,0.06);
        position: relative;
    }

    body.principal-page .sidebar-footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 24px;
        right: 24px;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
    }

    body.principal-page .sidebar-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 14px;
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
    }

    body.principal-page .sidebar-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        overflow: hidden;
        background: linear-gradient(135deg, #F5A623, rgba(245,166,35,0.4));
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Bebas Neue', cursive;
        font-size: 17px;
        color: #0A0A0F;
        box-shadow: 0 0 15px rgba(245,166,35,0.3);
        flex-shrink: 0;
    }

    body.principal-page .sidebar-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        display: block;
        border-radius: inherit;
    }

    body.principal-page .sidebar-user-name {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
        display: block;
    }

    body.principal-page .sidebar-user-role {
        font-size: 11px;
        color: #5A6070;
        display: block;
    }

    @media (max-width: 768px) {
        body.principal-page .sidebar {
            transform: translateX(-100%);
            box-shadow: 0 0 40px rgba(0,0,0,0.45);
            width: 80vw;
            max-width: 320px;
            min-width: 220px;
        }

        body.principal-page.sidebar-open .sidebar {
            transform: translateX(0);
        }

        body.principal-page.sidebar-open .sidebar-overlay,
        body.principal-page.sidebar-open .overlay {
            opacity: 1;
            pointer-events: auto;
            display: block;
        }
    }
</style>