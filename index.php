<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMACE TECH LAB Nursery and Primary School</title>
    <meta name="description" content="SAMACE TECH LAB Nursery and Primary School offers premium nursery and primary education with a safe learning environment, modern teaching, and technology-driven growth.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0F;
            --bg-secondary: #0F0F1A;
            --bg-card: rgba(255,255,255,0.04);
            --border-glass: rgba(255,255,255,0.08);
            --accent-gold: #F5A623;
            --accent-teal: #00D4B4;
            --accent-blue: #4A9EFF;
            --text-primary: #FFFFFF;
            --text-secondary: #A0A8B8;
            --text-muted: #5A6070;
            --glow-gold: 0 0 40px rgba(245,166,35,0.3);
            --glow-teal: 0 0 40px rgba(0,212,180,0.3);
            --glow-blue: 0 0 40px rgba(74,158,255,0.3);
            --radius-card: 20px;
            --radius-btn: 50px;
            --font-display: 'Bebas Neue', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            --container-width: 1180px;
            --nav-height: 84px;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            transition:
                background-color 0.5s cubic-bezier(0.23,1,0.32,1),
                color 0.5s cubic-bezier(0.23,1,0.32,1),
                border-color 0.5s cubic-bezier(0.23,1,0.32,1),
                box-shadow 0.5s cubic-bezier(0.23,1,0.32,1),
                backdrop-filter 0.5s ease !important;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: var(--font-body);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::selection,
        ::selection {
            background: rgba(245,166,35,0.3);
            color: #fff;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(245,166,35,0.4);
            border-radius: 999px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-gold);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button {
            border: 0;
            background: none;
            font: inherit;
            color: inherit;
            cursor: pointer;
        }

        img,
        svg,
        canvas {
            display: block;
            max-width: 100%;
        }

        .orb,
        span[style*="animation"],
        canvas {
            transition: none !important;
        }

        #backgroundCanvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        #particles-container {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: orbDrift 20s ease-in-out infinite alternate;
        }

        .orb-gold {
            top: -140px;
            left: -120px;
            width: 600px;
            height: 600px;
            filter: blur(180px);
            opacity: 0.08;
            background: radial-gradient(circle, rgba(245,166,35,0.7), transparent 70%);
        }

        .orb-teal {
            top: 12%;
            right: -120px;
            width: 500px;
            height: 500px;
            filter: blur(160px);
            opacity: 0.06;
            background: radial-gradient(circle, rgba(0,212,180,0.7), transparent 70%);
            animation-duration: 22s;
        }

        .orb-blue {
            bottom: -120px;
            left: 8%;
            width: 400px;
            height: 400px;
            filter: blur(140px);
            opacity: 0.07;
            background: radial-gradient(circle, rgba(74,158,255,0.75), transparent 70%);
            animation-duration: 18s;
        }

        .site-shell {
            position: relative;
            z-index: 1;
        }

        .container {
            width: min(calc(100% - 32px), var(--container-width));
            margin: 0 auto;
        }

        .glass-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(18,24,38,0.72), rgba(8,13,24,0.5));
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius-card);
            backdrop-filter: blur(22px) saturate(145%);
            -webkit-backdrop-filter: blur(22px) saturate(145%);
            transition: var(--transition);
            box-shadow: 0 28px 70px rgba(0,0,0,0.22), inset 0 1px 0 rgba(255,255,255,0.05), inset 0 -12px 40px rgba(74,158,255,0.05);
        }

        .glass-card::before,
        .glass-card::after {
            content: '';
            position: absolute;
            pointer-events: none;
            border-radius: inherit;
        }

        .glass-card::before {
            inset: 0;
            background: linear-gradient(145deg, rgba(255,255,255,0.14), rgba(255,255,255,0.02) 34%, rgba(255,255,255,0) 70%);
            opacity: 0.72;
        }

        .glass-card::after {
            top: -55%;
            left: -20%;
            width: 54%;
            height: 210%;
            background: linear-gradient(180deg, rgba(255,255,255,0), rgba(255,255,255,0.11), rgba(255,255,255,0));
            transform: rotate(22deg) translateX(-180%);
            opacity: 0;
            transition: transform 0.9s cubic-bezier(0.23, 1, 0.32, 1), opacity 0.4s ease;
        }

        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255,255,255,0.14);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .glass-card:hover::after {
            transform: rotate(22deg) translateX(320%);
            opacity: 1;
        }

        .btn-base {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 16px 36px;
            border-radius: var(--radius-btn);
            font-weight: 700;
            transition: var(--transition);
        }

        .btn-base:hover {
            transform: scale(1.04);
        }

        .btn-gold {
            background: var(--accent-gold);
            color: #0A0A0F;
            box-shadow: var(--glow-gold);
        }

        .btn-gold:hover {
            box-shadow: 0 0 50px rgba(245,166,35,0.4);
        }

        .ui-reactive {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            backface-visibility: hidden;
            transform: translateZ(0);
        }

        .ui-reactive::before {
            content: '';
            position: absolute;
            inset: 1px;
            border-radius: inherit;
            background: linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0.02) 42%, rgba(255,255,255,0.08));
            opacity: 0;
            transition: opacity 0.28s ease;
            pointer-events: none;
            z-index: 0;
        }

        .ui-reactive > * {
            position: relative;
            z-index: 1;
        }

        .ui-reactive:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 30px rgba(0,0,0,0.24), 0 0 0 1px rgba(255,255,255,0.05);
        }

        .ui-reactive:hover::before {
            opacity: 1;
        }

        .ui-reactive:active {
            transform: translateY(0) scale(0.985);
        }

        .ui-reactive:focus-visible {
            outline: none;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.08), 0 0 0 5px rgba(74,158,255,0.2), 0 16px 30px rgba(0,0,0,0.2);
        }

        .ui-ripple {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            transform: scale(0);
            background: radial-gradient(circle, rgba(255,255,255,0.42) 0%, rgba(255,255,255,0.18) 40%, rgba(255,255,255,0) 72%);
            animation: uiRipple 0.68s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }

        @keyframes uiRipple {
            from {
                transform: scale(0);
                opacity: 0.7;
            }
            to {
                transform: scale(1);
                opacity: 0;
            }
        }

        .btn-ghost {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.2);
            color: var(--text-primary);
        }

        .btn-ghost:hover {
            border-color: rgba(245,166,35,0.55);
            color: var(--accent-gold);
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            height: var(--nav-height);
            background: rgba(10,10,15,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: var(--transition);
        }

        .theme-toggle-wrap {
            position: fixed;
            top: 20px;
            right: 24px;
            z-index: 9999;
        }

        .theme-toggle {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            padding: 6px 10px;
            cursor: pointer;
            backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.4s cubic-bezier(0.23,1,0.32,1);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }

        .theme-toggle:hover {
            transform: scale(1.06);
            box-shadow: 0 0 20px rgba(245,166,35,0.3);
            border-color: rgba(245,166,35,0.3);
        }

        .toggle-track {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            position: relative;
        }

        .toggle-thumb {
            display: none;
        }

        .icon-moon,
        .icon-sun {
            font-size: 14px;
            transition: all 0.4s;
            line-height: 1;
        }

        [data-theme="dark"] .icon-sun { opacity: 0.3; }
        [data-theme="dark"] .icon-moon { opacity: 1; }
        [data-theme="light"] .icon-moon { opacity: 0.3; }
        [data-theme="light"] .icon-sun { opacity: 1; }

        [data-theme="light"] body {
            background: #F8F8F8;
            color: #1a1a2e;
        }

        [data-theme="light"] .theme-toggle {
            background: rgba(0,0,0,0.05);
            border-color: rgba(0,0,0,0.12);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        [data-theme="light"] .theme-toggle:hover {
            border-color: rgba(245,166,35,0.5);
            box-shadow: 0 0 20px rgba(245,166,35,0.2);
        }

        [data-theme="light"] .orb-gold {
            background: radial-gradient(circle, rgba(245,166,35,0.07), transparent 70%);
        }

        [data-theme="light"] .orb-teal {
            background: radial-gradient(circle, rgba(0,180,160,0.05), transparent 70%);
        }

        [data-theme="light"] .orb-blue {
            background: radial-gradient(circle, rgba(74,158,255,0.04), transparent 70%);
        }

        [data-theme="light"] .site-header {
            background: transparent;
            border-bottom: none;
            backdrop-filter: none;
            box-shadow: none;
        }

        [data-theme="light"] .site-header.scrolled {
            background: transparent;
            box-shadow: none;
        }

        [data-theme="light"] .nav-inner {
            background: rgba(255,255,255,0.85);
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 50px;
            backdrop-filter: blur(16px);
            box-shadow: 0 4px 30px rgba(0,0,0,0.08), 0 1px 0 rgba(255,255,255,0.9) inset;
            max-width: 680px;
            margin: 16px auto 0;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            gap: 32px;
            justify-content: center;
            height: auto;
            min-height: 64px;
        }

        [data-theme="light"] .brand {
            display: none;
        }

        [data-theme="light"] .nav-links-wrap {
            flex: 1;
            justify-content: center;
        }

        [data-theme="light"] .nav-links {
            gap: 28px;
        }

        [data-theme="light"] .nav-link {
            color: #555;
            font-size: 14px;
            font-weight: 500;
        }

        [data-theme="light"] .nav-link:hover {
            color: #1a1a2e;
        }

        [data-theme="light"] .nav-link.active {
            color: #1a1a2e;
            font-weight: 700;
        }

        [data-theme="light"] .nav-link.active::after {
            background: #1a1a2e;
        }

        [data-theme="light"] .nav-toggle {
            background: rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.1);
            color: #555;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        [data-theme="light"] .nav-toggle:hover {
            background: rgba(0,0,0,0.1);
            color: #1a1a2e;
        }

        [data-theme="light"] .hero-title,
        [data-theme="light"] .hero-line,
        [data-theme="light"] .hero-word,
        [data-theme="light"] .section-head h2,
        [data-theme="light"] .portal-card h3,
        [data-theme="light"] .why-card h3,
        [data-theme="light"] .footer-panel h3,
        [data-theme="light"] .footer-brand,
        [data-theme="light"] .feature-float h3 {
            color: #1a1a2e;
        }

        [data-theme="light"] .hero-word.gold,
        [data-theme="light"] .section-accent {
            color: #F5A623;
            text-shadow: none;
        }

        [data-theme="light"] .hero-subtext,
        [data-theme="light"] .section-head p,
        [data-theme="light"] .portal-card p,
        [data-theme="light"] .why-card p,
        [data-theme="light"] .footer-panel p,
        [data-theme="light"] .footer-panel a,
        [data-theme="light"] .footer-panel li,
        [data-theme="light"] .feature-float p {
            color: #666;
        }

        [data-theme="light"] .hero-badge {
            background: rgba(0,0,0,0.06);
            border-color: rgba(0,212,180,0.4);
            color: #1a1a2e;
            box-shadow: none;
        }

        [data-theme="light"] .btn-gold {
            background: linear-gradient(135deg, #F5A623, #e8950f);
            color: #0A0A0F;
            box-shadow: 0 4px 20px rgba(245,166,35,0.3);
        }

        [data-theme="light"] .btn-ghost {
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(0,0,0,0.12);
            color: #1a1a2e;
            backdrop-filter: blur(8px);
        }

        [data-theme="light"] .btn-ghost:hover {
            background: #fff;
            border-color: #F5A623;
            color: #F5A623;
        }

        [data-theme="light"] .glass-card,
        [data-theme="light"] .feature-float,
        [data-theme="light"] .feature-card,
        [data-theme="light"] .portal-card,
        [data-theme="light"] .why-card {
            background: rgba(255,255,255,0.75);
            border: 1px solid rgba(0,0,0,0.07);
            box-shadow: 0 8px 32px rgba(0,0,0,0.08);
            backdrop-filter: blur(16px);
        }

        [data-theme="light"] .glass-card:hover,
        [data-theme="light"] .portal-card:hover,
        [data-theme="light"] .why-card:hover,
        [data-theme="light"] .feature-float:hover {
            background: rgba(255,255,255,0.95);
            box-shadow: 0 16px 48px rgba(0,0,0,0.12);
        }

        [data-theme="light"] .stats-strip {
            background: rgba(255,255,255,0.7);
            border-color: rgba(0,0,0,0.06);
            backdrop-filter: blur(12px);
        }

        [data-theme="light"] .stat-label {
            color: #777;
        }

        [data-theme="light"] .stat-item:not(:last-child)::after {
            background: rgba(0,0,0,0.08);
        }

        [data-theme="light"] .footer {
            background: #f0f0f0;
            border-top: 1px solid rgba(0,0,0,0.08);
        }

        [data-theme="light"] .footer-bottom {
            border-color: rgba(0,0,0,0.08);
            color: #444;
        }

        [data-theme="light"] ::-webkit-scrollbar-track { background: #f0f0f0; }
        [data-theme="light"] ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); }
        [data-theme="light"] ::-webkit-scrollbar-thumb:hover { background: #F5A623; }
        [data-theme="light"] ::selection { background: rgba(245,166,35,0.2); color: #1a1a2e; }

        .site-header.scrolled {
            background: rgba(10,10,15,0.82);
            box-shadow: inset 0 -1px 0 rgba(245,166,35,0.2), 0 10px 40px rgba(0,0,0,0.22);
        }

        .nav-inner {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: max-content;
        }

        .brand-mark {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: rgba(245,166,35,0.1);
            border: 1px solid rgba(245,166,35,0.18);
            color: var(--accent-gold);
            box-shadow: var(--glow-gold);
            flex-shrink: 0;
        }

        .brand-title {
            display: block;
            font-family: var(--font-display);
            font-size: 1.9rem;
            color: var(--accent-gold);
            letter-spacing: 0.055em;
            line-height: 0.95;
        }

        .brand-subtitle {
            display: block;
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 0.78rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .nav-links-wrap {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 22px;
            align-items: center;
        }

        .nav-link {
            position: relative;
            color: var(--text-secondary);
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 0;
            height: 1px;
            background: var(--accent-gold);
            transition: width 0.3s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--accent-gold);
        }

        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }

        .nav-toggle {
            display: none;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            color: var(--accent-gold);
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(245,166,35,0.18);
        }

        .mobile-menu {
            display: none;
        }

        .hero {
            min-height: calc(100vh - var(--nav-height));
            display: flex;
            align-items: center;
            padding: 36px 0 56px;
            position: relative;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 30px;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border-radius: 999px;
            border: 1px solid rgba(0,212,180,0.32);
            box-shadow: var(--glow-teal);
            color: var(--accent-teal);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 22px;
        }

        .hero-badge::before {
            content: '\2726';
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(4.2rem, 9vw, 5.9rem);
            line-height: 0.9;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .hero-line {
            display: block;
            overflow: hidden;
        }

        .hero-word {
            display: inline-block;
            opacity: 0;
            transform: translateY(60px);
            animation: heroRise 0.85s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        }

        .hero-word + .hero-word {
            margin-left: 0.22em;
        }

        .hero-word.gold {
            color: var(--accent-gold);
            text-shadow: 0 0 26px rgba(245,166,35,0.22);
        }

        .hero-subtext {
            max-width: 520px;
            margin-top: 22px;
            color: var(--text-secondary);
            font-size: 1.05rem;
            line-height: 1.85;
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
            animation-delay: 0.45s;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 30px;
            opacity: 0;
            animation: fadeIn 0.8s ease forwards;
            animation-delay: 0.6s;
        }

        .hero-stack {
            position: relative;
            min-height: 520px;
        }

        .feature-float {
            position: absolute;
            width: min(100%, 340px);
            padding: 28px 24px 28px 30px;
        }

        .feature-float::before {
            content: '';
            position: absolute;
            left: 0;
            top: 18px;
            bottom: 18px;
            width: 4px;
            border-radius: 999px;
        }

        .feature-float h3 {
            font-family: var(--font-heading);
            font-size: 1.55rem;
            margin-bottom: 12px;
        }

        .feature-float p {
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .feature-float.one {
            top: 0;
            right: 0;
            transform: rotate(2deg);
            animation: floatCardA 5s ease-in-out infinite alternate;
        }

        .feature-float.one::before {
            background: var(--accent-teal);
            box-shadow: var(--glow-teal);
        }

        .feature-float.two {
            top: 184px;
            left: 0;
            transform: rotate(-1deg);
            animation: floatCardB 5.6s ease-in-out infinite alternate;
        }

        .feature-float.two::before {
            background: var(--accent-gold);
            box-shadow: var(--glow-gold);
        }

        .feature-float.three {
            right: 12px;
            bottom: 0;
            animation: floatCardC 6.2s ease-in-out infinite alternate;
        }

        .feature-float.three::before {
            background: var(--accent-blue);
            box-shadow: var(--glow-blue);
        }

        .feature-float:hover {
            transform: translateY(-12px) !important;
        }

        .scroll-indicator {
            position: absolute;
            left: 50%;
            bottom: 14px;
            transform: translateX(-50%);
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            color: var(--accent-gold);
            font-size: 1.4rem;
            animation: bounce 1.8s ease-in-out infinite;
            transition: opacity 0.3s ease;
        }

        .scroll-indicator.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .section {
            padding: 52px 0;
        }

        .stats-strip {
            background: rgba(255,255,255,0.03);
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .about-school-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            gap: 24px;
            align-items: stretch;
        }

        .about-school-card,
        .about-school-panel {
            padding: 38px 34px;
            border-radius: 24px;
        }

        .about-school-card h3,
        .about-school-panel h3 {
            font-family: var(--font-heading);
            font-size: 1.65rem;
            margin-bottom: 14px;
        }

        .about-school-card p,
        .about-school-panel p {
            color: var(--text-secondary);
            line-height: 1.85;
        }

        .about-school-copy {
            display: grid;
            gap: 16px;
        }

        .about-highlight-list {
            display: grid;
            gap: 14px;
            margin-top: 10px;
        }

        .about-highlight-item {
            display: grid;
            grid-template-columns: 44px minmax(0, 1fr);
            gap: 14px;
            align-items: start;
            padding: 14px 0;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .about-highlight-item:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .about-highlight-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: rgba(255,255,255,0.05);
            color: var(--accent-gold);
            box-shadow: var(--glow-gold);
            font-size: 1.2rem;
        }

        .about-highlight-item strong {
            display: block;
            font-size: 1rem;
            margin-bottom: 6px;
        }

        .about-metrics {
            display: grid;
            gap: 14px;
            margin-top: 22px;
        }

        .about-metric {
            padding: 18px 20px;
            border-radius: 18px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .about-metric-value {
            display: block;
            font-family: var(--font-display);
            font-size: 2.3rem;
            letter-spacing: 0.05em;
            color: var(--accent-gold);
            line-height: 1;
        }

        .about-metric-label {
            display: block;
            margin-top: 8px;
            color: var(--text-secondary);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.82rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .stat-item {
            position: relative;
            padding: 30px 20px;
            text-align: center;
        }

        .stat-item:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 24px;
            right: 0;
            width: 1px;
            height: calc(100% - 48px);
            background: rgba(255,255,255,0.08);
        }

        .stat-number {
            display: block;
            font-family: var(--font-display);
            font-size: clamp(3rem, 6vw, 3.5rem);
            line-height: 1;
            color: var(--accent-gold);
            letter-spacing: 0.05em;
        }

        .stat-label {
            margin-top: 10px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .section-head {
            text-align: center;
            margin-bottom: 36px;
        }

        .section-head h2 {
            font-family: var(--font-display);
            font-size: clamp(2.8rem, 6vw, 4rem);
            line-height: 0.95;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .section-head p {
            width: min(100%, 720px);
            margin: 14px auto 0;
            color: var(--text-secondary);
            line-height: 1.85;
        }

        .section-accent {
            width: 80px;
            height: 4px;
            margin: 14px auto 0;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), transparent);
            box-shadow: var(--glow-gold);
        }

        .portal-grid,
        .why-grid,
        .footer-grid {
            display: grid;
            gap: 22px;
        }

        .portal-grid,
        .why-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .portal-card,
        .why-card,
        .footer-panel {
            padding: 48px 36px;
            min-height: 100%;
            border-radius: 24px;
        }

        .portal-card {
            animation: pulseGlow 3s ease-in-out infinite;
        }

        .portal-card:hover,
        .why-card:hover {
            transform: translateY(-12px);
        }

        .portal-icon,
        .why-icon {
            display: grid;
            place-items: center;
            width: 82px;
            height: 82px;
            border-radius: 22px;
            font-size: 2.8rem;
            margin-bottom: 22px;
        }

        .portal-card h3,
        .why-card h3,
        .footer-panel h3 {
            font-family: var(--font-heading);
            font-size: 1.6rem;
            margin-bottom: 12px;
        }

        .portal-card p,
        .why-card p,
        .footer-panel p,
        .footer-panel a,
        .footer-panel li {
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .portal-card.principal {
            border-color: rgba(245,166,35,0.2);
        }

        .portal-card.principal:hover {
            border-color: rgba(245,166,35,0.5);
            box-shadow: var(--glow-gold);
        }

        .portal-card.principal .portal-icon {
            background: rgba(245,166,35,0.1);
            color: var(--accent-gold);
            box-shadow: var(--glow-gold);
        }

        .portal-card.parent {
            border-color: rgba(0,212,180,0.2);
        }

        .portal-card.parent:hover {
            border-color: rgba(0,212,180,0.5);
            box-shadow: var(--glow-teal);
        }

        .portal-card.parent .portal-icon {
            background: rgba(0,212,180,0.1);
            color: var(--accent-teal);
            box-shadow: var(--glow-teal);
        }

        .portal-card.teacher {
            border-color: rgba(74,158,255,0.2);
        }

        .portal-card.teacher:hover {
            border-color: rgba(74,158,255,0.48);
            box-shadow: var(--glow-blue);
        }

        .portal-card.teacher .portal-icon {
            background: rgba(74,158,255,0.1);
            color: var(--accent-blue);
            box-shadow: var(--glow-blue);
        }

        .portal-button {
            margin-top: 24px;
            padding: 15px 24px;
            border-radius: 999px;
            font-weight: 700;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .portal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 24px;
        }

        .portal-actions .portal-button {
            margin-top: 0;
            width: 100%;
            min-width: 0;
            padding-left: 18px;
            padding-right: 18px;
        }

        .portal-actions .portal-button:only-child {
            width: auto;
            min-width: 180px;
            max-width: 220px;
            margin-left: auto;
            margin-right: auto;
        }

        .portal-card.principal .portal-actions {
            grid-template-columns: 1fr;
            justify-items: center;
        }

        .portal-button:hover {
            transform: scale(1.03);
        }

        .portal-button.principal {
            background: var(--accent-gold);
            color: #0A0A0F;
        }

        .portal-button.parent {
            border: 1px solid rgba(0,212,180,0.5);
            color: var(--accent-teal);
        }

        .portal-button.parent:hover {
            background: var(--accent-teal);
            color: #08100F;
        }

        .portal-button.teacher {
            border: 1px solid rgba(74,158,255,0.5);
            color: var(--accent-blue);
        }

        .portal-button.teacher:hover {
            background: var(--accent-blue);
            color: #FFFFFF;
        }

        .why-card {
            border-top: 3px solid rgba(0,212,180,0.52);
        }

        .why-card:hover {
            box-shadow: var(--glow-teal);
        }

        .why-icon {
            width: 64px;
            height: 64px;
            font-size: 1.8rem;
            background: rgba(255,255,255,0.06);
        }

        .footer {
            margin-top: 24px;
            background: #080810;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer-grid {
            grid-template-columns: 1.1fr 0.8fr 0.8fr;
            padding: 42px 0 30px;
        }

        .footer-brand {
            font-family: var(--font-display);
            font-size: 2rem;
            letter-spacing: 0.05em;
            color: var(--accent-gold);
        }

        .footer-panel ul {
            list-style: none;
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .socials {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .social-chip {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
        }

        .footer-bottom {
            padding: 18px 0 26px;
            text-align: center;
            color: var(--text-muted);
            border-top: 1px solid rgba(245,166,35,0.22);
        }

        .reveal {
            opacity: 0;
            transform: translate3d(0, 40px, 0) scale(0.985);
            transition: opacity 0.8s cubic-bezier(0.23, 1, 0.32, 1), transform 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .reveal.visible {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }

        .reveal[data-delay="1"] { transition-delay: 0.08s; }
        .reveal[data-delay="2"] { transition-delay: 0.16s; }
        .reveal[data-delay="3"] { transition-delay: 0.24s; }

        @keyframes heroRise {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        @keyframes floatCardA {
            from { transform: translateY(0) rotate(2deg); }
            to { transform: translateY(-8px) rotate(2deg); }
        }

        @keyframes floatCardB {
            from { transform: translateY(0) rotate(-1deg); }
            to { transform: translateY(-8px) rotate(-1deg); }
        }

        @keyframes floatCardC {
            from { transform: translateY(0); }
            to { transform: translateY(-8px); }
        }

        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 20px 60px rgba(0,0,0,0.24);
            }
            50% {
                box-shadow: 0 20px 70px rgba(255,255,255,0.03);
            }
        }

        @keyframes pageFloat {
            from {
                transform: translate3d(0, 0, 0);
            }
            to {
                transform: translate3d(0, -8px, 0);
            }
        }

        .motion-float {
            animation: pageFloat 8.5s ease-in-out infinite alternate;
        }

        .motion-float:nth-of-type(2n) {
            animation-duration: 10s;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateX(-50%) translateY(0);
            }
            50% {
                transform: translateX(-50%) translateY(10px);
            }
        }

        @keyframes orbDrift {
            from { transform: translate(0, 0); }
            to { transform: translate(60px, 40px); }
        }

        @keyframes floatDot {
            0% { transform:translate(0,0) scale(1); opacity:.35; }
            33% { transform:translate(10px,-15px) scale(1.2); opacity:.8; }
            66% { transform:translate(-8px,8px) scale(.85); opacity:.25; }
            100% { transform:translate(15px,-20px) scale(1); opacity:.45; }
        }

        @media (max-width: 1200px) {
            .hero-grid {
                grid-template-columns: 1fr;
            }

            .hero-stack {
                min-height: 460px;
            }
        }

        @media (max-width: 1024px) {
            .portal-grid,
            .why-grid,
            .stats-grid,
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            :root {
                --nav-height: 78px;
            }

            .theme-toggle-wrap {
                top: 14px;
                right: 14px;
            }

            .theme-toggle {
                padding: 5px 9px;
            }

            .nav-links-wrap {
                display: none;
            }

            .nav-toggle {
                display: inline-grid;
                place-items: center;
            }

            .mobile-menu {
                display: block;
                position: fixed;
                top: var(--nav-height);
                left: 0;
                right: 0;
                z-index: 19;
                max-height: 0;
                overflow: hidden;
                background: rgba(10,10,15,0.92);
                border-bottom: 1px solid rgba(255,255,255,0.06);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                transition: max-height 0.35s ease;
            }

            [data-theme="light"] .mobile-menu {
                background: rgba(255,255,255,0.92);
                border-bottom: 1px solid rgba(0,0,0,0.06);
            }

            [data-theme="light"] .mobile-link {
                background: rgba(0,0,0,0.04);
                color: #444;
            }

            .mobile-menu.open {
                max-height: 360px;
            }

            .mobile-menu-inner {
                padding: 16px;
                display: grid;
                gap: 10px;
            }

            .mobile-link,
            .mobile-portal {
                opacity: 0;
                transform: translateY(-10px);
                transition: opacity 0.3s ease, transform 0.3s ease;
            }

            .mobile-menu.open .mobile-link,
            .mobile-menu.open .mobile-portal {
                opacity: 1;
                transform: translateY(0);
            }

            .mobile-link {
                padding: 14px 16px;
                border-radius: 16px;
                background: rgba(255,255,255,0.03);
                color: var(--text-secondary);
            }

            .mobile-link:nth-child(1) { transition-delay: 0.04s; }
            .mobile-link:nth-child(2) { transition-delay: 0.08s; }
            .mobile-link:nth-child(3) { transition-delay: 0.12s; }
            .mobile-link:nth-child(4) { transition-delay: 0.16s; }
            .mobile-portal { transition-delay: 0.2s; }

            .hero {
                min-height: auto;
                padding-top: 28px;
            }

            .hero-title {
                font-size: clamp(3.4rem, 16vw, 5rem);
            }

            .hero-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .hero-stack {
                min-height: auto;
                display: grid;
                gap: 16px;
            }

            .feature-float {
                position: relative;
                top: auto;
                left: auto;
                right: auto;
                bottom: auto;
                width: 100%;
                transform: none !important;
                animation: none !important;
            }

            .portal-grid,
            .why-grid,
            .stats-grid,
            .about-school-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .stat-item:not(:last-child)::after {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .theme-toggle-wrap {
                top: 12px;
                right: 12px;
            }

            .theme-toggle {
                padding: 5px 8px;
                gap: 4px;
            }

            .icon-moon,
            .icon-sun {
                font-size: 13px;
            }

            .container {
                width: min(calc(100% - 24px), var(--container-width));
            }

            .portal-card,
            .about-school-card,
            .about-school-panel,
            .why-card,
            .footer-panel,
            .feature-float {
                padding: 28px 22px;
            }

            .btn-base,
            .portal-button {
                width: 100%;
            }

            .portal-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *, *::before, *::after {
                transition: none !important;
            }

            .hero-word,
            .hero-subtext,
            .hero-actions,
            .feature-float,
            .portal-card,
            .why-card,
            .footer-panel,
            .motion-float,
            .reveal,
            .reveal.visible,
            .scroll-indicator {
                animation: none !important;
            }

            .orb {
                animation-duration: 32s !important;
            }

            #particles-container span {
                animation-duration: 24s !important;
            }

            .scroll-indicator {
                display: none !important;
            }

            .reveal,
            .hero-word,
            .hero-subtext,
            .hero-actions {
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body>

    <canvas id="backgroundCanvas" aria-hidden="true"></canvas>
    <div id="particles-container" aria-hidden="true"></div>
    <div class="orb orb-gold" aria-hidden="true"></div>
    <div class="orb orb-teal" aria-hidden="true"></div>
    <div class="orb orb-blue" aria-hidden="true"></div>

    <div class="site-shell">
        <header class="site-header" id="siteHeader">
            <div class="container nav-inner">
                <a class="brand" href="#home" aria-label="SAMACE TECH LAB Nursery and Primary School home">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 64 64" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5 22L32 10L59 22L32 34L5 22Z" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
                            <path d="M15 27V39C15 43 24 48 32 48C40 48 49 43 49 39V27" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            <path d="M59 22V36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span>
                        <span class="brand-title">SAMACE TECH LAB</span>
                        <span class="brand-subtitle">Nursery &amp; Primary School</span>
                    </span>
                </a>

                <div class="nav-links-wrap">
                    <nav aria-label="Primary navigation">
                        <ul class="nav-links">
                            <li><a class="nav-link active" href="#home">Home</a></li>
                            <li><a class="nav-link" href="#about">About</a></li>
                            <li><a class="nav-link" href="#footer">Contact</a></li>
                        </ul>
                    </nav>
                    <a class="btn-base btn-ghost" href="#portals">Portal Login</a>
                </div>

                <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="mobileMenu">&#9776;</button>
            </div>
        </header>

        <div class="mobile-menu" id="mobileMenu">
            <div class="container mobile-menu-inner">
                <a class="mobile-link" href="#home">Home</a>
                <a class="mobile-link" href="#about">About</a>
                <a class="mobile-link" href="#footer">Contact</a>
                <a class="btn-base btn-ghost mobile-portal" href="#portals">Portal Login</a>
            </div>
        </div>

        <main>
            <section class="hero" id="home">
                <div class="container hero-grid">
                    <div>
                        <div class="hero-badge glass-card">Trusted Nursery &amp; Primary Education in Nigeria</div>
                        <h1 class="hero-title" aria-label="Nurturing Minds, Building Futures">
                            <span class="hero-line">
                                <span class="hero-word" style="animation-delay:0.05s;">Nurturing</span>
                                <span class="hero-word" style="animation-delay:0.15s;">Minds,</span>
                            </span>
                            <span class="hero-line">
                                <span class="hero-word gold" style="animation-delay:0.25s;">Building</span>
                                <span class="hero-word gold" style="animation-delay:0.35s;">Futures</span>
                            </span>
                        </h1>
                        <p class="hero-subtext">SAMACE TECH LAB Nursery and Primary School blends strong academics, caring guidance, and future-ready learning experiences to help children grow with confidence, curiosity, and character.</p>
                        <div class="hero-actions">
                            <a class="btn-base btn-gold" href="about.php">Learn More About Us</a>
                            <a class="btn-base btn-ghost" href="index.php#footer">Contact School</a>
                        </div>
                    </div>

                    <div class="hero-stack">
                        <article class="feature-float glass-card one reveal" data-delay="1">
                            <h3>&#10022; Bright Learning Spaces</h3>
                            <p>Modern classrooms and welcoming learning environments designed to help every child feel inspired and supported.</p>
                        </article>
                        <article class="feature-float glass-card two reveal" data-delay="2">
                            <h3>&#127912; Joyful Learning</h3>
                            <p>A rich balance of creativity, routine, and foundational teaching that keeps learning purposeful and exciting.</p>
                        </article>
                        <article class="feature-float glass-card three reveal" data-delay="3">
                            <h3>&#128737; Safe Community</h3>
                            <p>A secure and nurturing school culture where children are protected, known, and encouraged to thrive.</p>
                        </article>
                    </div>
                </div>

                <a class="scroll-indicator" id="scrollIndicator" href="#about" aria-label="Scroll to next section">
                    <span>&#8964;</span>
                    <span>&#8964;</span>
                </a>
            </section>

            <section class="section" id="about">
                <div class="container">
                    <div class="section-head reveal">
                        <h2>About School</h2>
                        <div class="section-accent"></div>
                        <p>Learn what makes SAMACE TECH LAB Nursery and Primary School a disciplined, caring, and future-ready learning community.</p>
                    </div>

                    <div class="about-school-grid">
                        <article class="about-school-card glass-card reveal" data-delay="1">
                            <h3>A School Built For Growth</h3>
                            <div class="about-school-copy">
                                <p>SAMACE TECH LAB Nursery and Primary School is built around strong teaching, close guidance, and a safe environment where children can grow in confidence, character, and academic ability.</p>
                                <p>We combine foundational learning with creativity, structure, and technology-aware teaching so every child develops the discipline and curiosity needed for the future.</p>
                            </div>

                            <div class="about-highlight-list">
                                <div class="about-highlight-item">
                                    <div class="about-highlight-icon">★</div>
                                    <div>
                                        <strong>Strong Academic Foundation</strong>
                                        <p>Children receive focused instruction in literacy, numeracy, and practical thinking from the early years upward.</p>
                                    </div>
                                </div>
                                <div class="about-highlight-item">
                                    <div class="about-highlight-icon">✓</div>
                                    <div>
                                        <strong>Safe And Supportive Culture</strong>
                                        <p>We create a calm, secure school atmosphere where learners feel known, protected, and encouraged every day.</p>
                                    </div>
                                </div>
                                <div class="about-highlight-item">
                                    <div class="about-highlight-icon">⌘</div>
                                    <div>
                                        <strong>Future-Ready Learning</strong>
                                        <p>Modern tools and technology-aware teaching help pupils build confidence beyond the classroom.</p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <aside class="about-school-panel glass-card reveal" data-delay="2">
                            <h3>School Snapshot</h3>
                            <p>Our approach blends academic seriousness with warmth, discipline, and community partnership.</p>
                            <div class="about-metrics">
                                <div class="about-metric">
                                    <span class="about-metric-value">15+</span>
                                    <span class="about-metric-label">Years Of Excellence</span>
                                </div>
                                <div class="about-metric">
                                    <span class="about-metric-value">30+</span>
                                    <span class="about-metric-label">Qualified Teachers</span>
                                </div>
                                <div class="about-metric">
                                    <span class="about-metric-value">500+</span>
                                    <span class="about-metric-label">Students Enrolled</span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>

            <section class="section stats-strip" id="highlights">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-number" data-count="500" data-suffix="+">0</span>
                            <span class="stat-label">Students Enrolled</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="30" data-suffix="+">0</span>
                            <span class="stat-label">Qualified Teachers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number" data-count="15" data-suffix="+">0</span>
                            <span class="stat-label">Years of Excellence</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number">Top Rated</span>
                            <span class="stat-label">School Lagos</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="portals">
                <div class="container">
                    <div class="section-head reveal">
                        <h2>Access Your Portal</h2>
                        <div class="section-accent"></div>
                        <p>Secure portal access for principals, parents, and teachers, all built within the same school communication ecosystem.</p>
                    </div>

                    <div class="portal-grid">
                        <article class="portal-card glass-card principal reveal" data-delay="1">
                            <div class="portal-icon">&#127891;</div>
                            <h3>Principal</h3>
                            <p>Full school management, announcements, reporting, staff coordination, and leadership oversight.</p>
                            <div class="portal-actions">
                                <a class="portal-button principal" href="principal-login.php">Login</a>
                            </div>
                        </article>
                        <article class="portal-card glass-card parent reveal" data-delay="2">
                            <div class="portal-icon">&#128106;</div>
                            <h3>Parent</h3>
                            <p>Stay connected, view announcements, receive updates, and message teachers when needed.</p>
                            <div class="portal-actions">
                                <a class="portal-button parent" href="parent-login.php">Login</a>
                                <a class="portal-button btn-ghost" href="parent-signup.php">Register</a>
                            </div>
                        </article>
                        <article class="portal-card glass-card teacher reveal" data-delay="3">
                            <div class="portal-icon">&#128105;&#8205;&#127979;</div>
                            <h3>Teacher</h3>
                            <p>Access your workspace, communicate clearly, and manage your class with confidence.</p>
                            <div class="portal-actions">
                                <a class="portal-button teacher" href="teacher-login.php">Login</a>
                                <a class="portal-button btn-ghost" href="teacher-signup.php">Register</a>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="container">
                    <div class="section-head reveal">
                        <h2>Why Choose Us</h2>
                        <div class="section-accent"></div>
                        <p>We pair strong values with structured teaching, secure spaces, and smart learning tools that prepare children for tomorrow.</p>
                    </div>

                    <div class="why-grid">
                        <article class="why-card glass-card reveal" data-delay="1">
                            <div class="why-icon">&#128161;</div>
                            <h3>Quality Education</h3>
                            <p>Strong curriculum delivery that blends traditional foundations with modern classroom thinking.</p>
                        </article>
                        <article class="why-card glass-card reveal" data-delay="2">
                            <div class="why-icon">&#128274;</div>
                            <h3>Safe Environment</h3>
                            <p>Secure, nurturing spaces where every child feels protected, seen, and ready to learn.</p>
                        </article>
                        <article class="why-card glass-card reveal" data-delay="3">
                            <div class="why-icon">&#128187;</div>
                            <h3>Tech-Driven Learning</h3>
                            <p>Smart classrooms and digital tools that develop 21st-century readiness from the early years.</p>
                        </article>
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer" id="footer">
            <div class="container footer-grid">
                <section class="footer-panel glass-card reveal" data-delay="1">
                    <div class="footer-brand">SAMACE TECH LAB</div>
                    <p style="margin-top:14px;">Premium nursery and primary education focused on academic excellence, safe growth, and a strong future for every learner.</p>
                    <ul>
                        <li>Address: Lagos, Nigeria</li>
                        <li>Phone: +234 000 000 0000</li>
                        <li>Email: info@samacetechlabschool.com</li>
                    </ul>
                </section>
                <section class="footer-panel glass-card reveal" data-delay="2">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="academics.php">Academics</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="#portals">Portals</a></li>
                    </ul>
                </section>
                <section class="footer-panel glass-card reveal" data-delay="3">
                    <h3>Connect With Us</h3>
                    <p>Stay close to the school community through our official channels.</p>
                    <div class="socials">
                        <span class="social-chip glass-card">F</span>
                        <span class="social-chip glass-card">I</span>
                        <span class="social-chip glass-card">L</span>
                        <span class="social-chip glass-card">Y</span>
                    </div>
                </section>
            </div>
            <div class="container footer-bottom">&copy; 2026 SAMACE TECH LAB Nursery and Primary School. All rights reserved.</div>
        </footer>
    </div>

    <script>
        (function () {
            const THEME_KEY = 'samace_theme';
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const canvas = document.getElementById('backgroundCanvas');
            const particlesContainer = document.getElementById('particles-container');
            const context = canvas ? canvas.getContext('2d') : null;
            const header = document.getElementById('siteHeader');
            const navToggle = document.getElementById('navToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            // Theme toggle removed; always dark mode
            const scrollIndicator = document.getElementById('scrollIndicator');
            let reveals = [];
            const counters = Array.from(document.querySelectorAll('[data-count]'));
            const navLinks = Array.from(document.querySelectorAll('.nav-link'));
            const anchorLinks = Array.from(document.querySelectorAll('a[href^="#"]'));
            const pointer = { x: null, y: null, active: false };
            let particles = [];
            let comets = [];
            let width = 0;
            let height = 0;
            let particleCount = prefersReducedMotion ? 24 : (window.innerWidth < 768 ? 60 : 120);

            if (particlesContainer) {
                const dotCount = prefersReducedMotion ? 18 : (window.innerWidth < 768 ? 36 : 72);
                const dotColors = ['rgba(245,166,35,0.34)', 'rgba(0,212,180,0.28)', 'rgba(74,158,255,0.26)', 'rgba(255,255,255,0.22)'];

                for (let index = 0; index < dotCount; index += 1) {
                    const dot = document.createElement('span');
                    const size = Math.random() * 4 + 2;
                    dot.style.cssText = `position:absolute; width:${size}px; height:${size}px; border-radius:50%; background:${dotColors[Math.floor(Math.random() * dotColors.length)]}; box-shadow:0 0 ${size * 10}px currentColor; color:${dotColors[Math.floor(Math.random() * dotColors.length)]}; top:${Math.random() * 100}%; left:${Math.random() * 100}%; animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;`;
                    particlesContainer.appendChild(dot);
                }
            }

            function getTheme() {
                return 'dark';
            }

            function getParticlePalette(theme) {
                return theme === 'light'
                    ? ['rgba(0,0,0,0.1)', 'rgba(0,0,0,0.07)', 'rgba(100,100,100,0.1)', 'rgba(50,50,50,0.08)']
                    : ['#F5A623', '#00D4B4', '#4A9EFF', 'rgba(255,255,255,0.6)'];
            }

            function updateParticleColors(theme) {
                const palette = getParticlePalette(theme);
                particles.forEach((particle) => {
                    particle.color = palette[Math.floor(Math.random() * palette.length)];
                });
            }

            function toRgba(color, alpha) {
                if (color.startsWith('rgba(')) {
                    const parts = color.slice(5, -1).split(',').map((part) => part.trim());
                    return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
                }

                if (color.startsWith('rgb(')) {
                    const parts = color.slice(4, -1).split(',').map((part) => part.trim());
                    return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
                }

                if (color.startsWith('#')) {
                    let hex = color.slice(1);
                    if (hex.length === 3) {
                        hex = hex.split('').map((char) => char + char).join('');
                    }
                    const red = parseInt(hex.slice(0, 2), 16);
                    const green = parseInt(hex.slice(2, 4), 16);
                    const blue = parseInt(hex.slice(4, 6), 16);
                    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
                }

                return color;
            }

            function syncOrbOpacity(theme) {
                document.querySelectorAll('.orb').forEach((orb) => {
                    orb.style.opacity = theme === 'light' ? '0.6' : '';
                });
            }

            function applyTheme(theme, withFlash = true) {
                document.documentElement.setAttribute('data-theme', 'dark');
                // No localStorage, no light mode logic
            }

            function toggleTheme() {
                applyTheme(getTheme() === 'dark' ? 'light' : 'dark');
            }

            function updateHeaderState() {
                const scrolled = window.scrollY > 80;
                header.classList.toggle('scrolled', scrolled);
                if (scrollIndicator) {
                    scrollIndicator.classList.toggle('hidden', window.scrollY > 200);
                }

                const sections = ['home', 'about', 'portals', 'footer'];
                let activeSection = 'home';
                sections.forEach((sectionId) => {
                    const section = document.getElementById(sectionId);
                    if (!section) {
                        return;
                    }
                    const rect = section.getBoundingClientRect();
                    if (rect.top <= window.innerHeight * 0.35 && rect.bottom >= window.innerHeight * 0.35) {
                        activeSection = sectionId;
                    }
                });

                navLinks.forEach((link) => {
                    const target = (link.getAttribute('href') || '').slice(1);
                    link.classList.toggle('active', target === activeSection);
                });
            }

            updateHeaderState();
            window.addEventListener('scroll', updateHeaderState, { passive: true });

            function getInteractiveSelector() {
                return [
                    'button',
                    '.btn-base',
                    '.portal-button',
                    '.nav-link',
                    '.mobile-link',
                    '.nav-toggle',
                    '.scroll-indicator'
                ].join(', ');
            }

            function markInteractiveElements(root) {
                if (!(root instanceof Element || root instanceof Document)) {
                    return;
                }

                root.querySelectorAll(getInteractiveSelector()).forEach((element) => {
                    if (element.closest('#particles-container')) {
                        return;
                    }
                    element.classList.add('ui-reactive');
                });
            }

            function getMotionSelector() {
                return [
                    '.glass-card',
                    '.portal-card',
                    '.why-card',
                    '.footer-panel',
                    '.feature-float',
                    '.hero-badge',
                    '.section-head'
                ].join(', ');
            }

            function markMotionElements(root) {
                if (!(root instanceof Element || root instanceof Document)) {
                    return;
                }

                root.querySelectorAll(getMotionSelector()).forEach((element, index) => {
                    if (element.closest('#particles-container')) {
                        return;
                    }
                    if (!element.classList.contains('reveal')) {
                        element.classList.add('reveal');
                    }
                    if (!element.classList.contains('section-head')) {
                        element.classList.add('motion-float');
                    }
                    if (!element.dataset.delay) {
                        element.dataset.delay = String((index % 3) + 1);
                    }
                });
            }

            function createRipple(target, clientX, clientY) {
                const rect = target.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height) * 1.15;
                const ripple = document.createElement('span');
                ripple.className = 'ui-ripple';
                ripple.style.width = size + 'px';
                ripple.style.height = size + 'px';
                ripple.style.left = clientX - rect.left - size / 2 + 'px';
                ripple.style.top = clientY - rect.top - size / 2 + 'px';
                target.appendChild(ripple);
                ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
            }

            markInteractiveElements(document);
            markMotionElements(document);
            reveals = Array.from(document.querySelectorAll('.reveal'));

            if (!prefersReducedMotion) {
                document.addEventListener('pointerdown', (event) => {
                    const target = event.target instanceof Element ? event.target.closest(getInteractiveSelector()) : null;
                    if (!target || target.closest('#particles-container')) {
                        return;
                    }
                    createRipple(target, event.clientX, event.clientY);
                }, { passive: true });
            }

            // Always dark mode
            document.documentElement.setAttribute('data-theme', 'dark');
            syncOrbOpacity('dark');

            if (navToggle && mobileMenu) {
                navToggle.addEventListener('click', () => {
                    const expanded = navToggle.getAttribute('aria-expanded') === 'true';
                    navToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    mobileMenu.classList.toggle('open', !expanded);
                });
            }

            anchorLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    const href = link.getAttribute('href') || '';
                    const target = document.querySelector(href);
                    if (!target) {
                        return;
                    }
                    event.preventDefault();
                    target.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
                    if (mobileMenu && mobileMenu.classList.contains('open')) {
                        mobileMenu.classList.remove('open');
                        navToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });

            if (!prefersReducedMotion) {
                const revealObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('visible');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.18 });

                reveals.forEach((item) => revealObserver.observe(item));

                const counterObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry.isIntersecting || entry.target.dataset.done === 'true') {
                            return;
                        }
                        const node = entry.target;
                        const target = Number(node.dataset.count || 0);
                        const suffix = node.dataset.suffix || '';
                        const startTime = performance.now();
                        const duration = 1400;

                        function step(now) {
                            const progress = Math.min((now - startTime) / duration, 1);
                            node.textContent = Math.floor(target * progress) + suffix;
                            if (progress < 1) {
                                requestAnimationFrame(step);
                            } else {
                                node.textContent = target + suffix;
                                node.dataset.done = 'true';
                            }
                        }

                        requestAnimationFrame(step);
                        counterObserver.unobserve(node);
                    });
                }, { threshold: 0.45 });

                counters.forEach((counter) => counterObserver.observe(counter));
            } else {
                reveals.forEach((item) => item.classList.add('visible'));
                counters.forEach((counter) => {
                    counter.textContent = (counter.dataset.count || '0') + (counter.dataset.suffix || '');
                });
            }

            if (!canvas || !context) {
                return;
            }

            function resizeCanvas() {
                width = window.innerWidth;
                height = window.innerHeight;
                particleCount = prefersReducedMotion ? 24 : (width < 768 ? 60 : 120);
                const ratio = window.devicePixelRatio || 1;
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                canvas.style.width = width + 'px';
                canvas.style.height = height + 'px';
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                particles = Array.from({ length: particleCount }, createParticle);
                comets = Array.from({ length: prefersReducedMotion ? 0 : (width < 768 ? 1 : 2) }, createComet);
            }

            function createParticle() {
                const angle = Math.random() * Math.PI * 2;
                const speed = 0.12 + Math.random() * 0.4;
                const palette = getParticlePalette(getTheme());
                return {
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: 1.2 + Math.random() * 3.8,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    color: palette[Math.floor(Math.random() * palette.length)],
                    alpha: 0.35 + Math.random() * 0.5,
                    pulse: Math.random() * Math.PI * 2,
                    glow: 18 + Math.random() * 28
                };
            }

            function createComet() {
                const fromLeft = Math.random() > 0.5;
                return {
                    x: fromLeft ? -120 : width + 120,
                    y: Math.random() * height * 0.55,
                    vx: fromLeft ? 5 + Math.random() * 2 : -(5 + Math.random() * 2),
                    vy: 1.1 + Math.random() * 1.8,
                    length: 120 + Math.random() * 80,
                    life: 0,
                    maxLife: 90 + Math.random() * 50,
                    color: ['rgba(245,166,35,0.9)', 'rgba(0,212,180,0.85)', 'rgba(74,158,255,0.85)'][Math.floor(Math.random() * 3)]
                };
            }

            function updateParticle(particle) {
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.pulse += 0.02;

                if (particle.x < -10) particle.x = width + 10;
                if (particle.x > width + 10) particle.x = -10;
                if (particle.y < -10) particle.y = height + 10;
                if (particle.y > height + 10) particle.y = -10;

                if (pointer.active && pointer.x !== null && pointer.y !== null) {
                    const dx = particle.x - pointer.x;
                    const dy = particle.y - pointer.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < 150 && distance > 0) {
                        const push = (150 - distance) / 150;
                        particle.x += (dx / distance) * push * 2.5;
                        particle.y += (dy / distance) * push * 2.5;
                    }
                }
            }

            function updateComet(comet, index) {
                comet.x += comet.vx;
                comet.y += comet.vy;
                comet.life += 1;

                if (comet.life > comet.maxLife || comet.y > height + 80 || comet.x < -220 || comet.x > width + 220) {
                    comets[index] = createComet();
                }
            }

            function drawBackdropGlow() {
                const gradient = context.createRadialGradient(width * 0.5, height * 0.28, 0, width * 0.5, height * 0.28, Math.max(width, height) * 0.72);
                gradient.addColorStop(0, 'rgba(74,158,255,0.10)');
                gradient.addColorStop(0.45, 'rgba(0,212,180,0.08)');
                gradient.addColorStop(0.75, 'rgba(245,166,35,0.05)');
                gradient.addColorStop(1, 'rgba(10,10,15,0)');
                context.fillStyle = gradient;
                context.fillRect(0, 0, width, height);
            }

            function drawScene() {
                context.clearRect(0, 0, width, height);
                drawBackdropGlow();

                for (let i = 0; i < particles.length; i += 1) {
                    const particle = particles[i];
                    updateParticle(particle);

                    const pulseScale = 0.82 + ((Math.sin(particle.pulse) + 1) / 2) * 0.5;
                    const glowSize = particle.glow * pulseScale;
                    const glow = context.createRadialGradient(particle.x, particle.y, 0, particle.x, particle.y, glowSize);
                    glow.addColorStop(0, toRgba(particle.color, particle.alpha * 0.28));
                    glow.addColorStop(1, 'rgba(0,0,0,0)');
                    context.beginPath();
                    context.fillStyle = glow;
                    context.arc(particle.x, particle.y, glowSize, 0, Math.PI * 2);
                    context.fill();

                    context.beginPath();
                    context.fillStyle = toRgba(particle.color, particle.alpha);
                    context.arc(particle.x, particle.y, particle.size * pulseScale, 0, Math.PI * 2);
                    context.fill();

                    for (let j = i + 1; j < particles.length; j += 1) {
                        const other = particles[j];
                        const dx = particle.x - other.x;
                        const dy = particle.y - other.y;
                        const distance = Math.sqrt(dx * dx + dy * dy);
                        if (distance <= 140) {
                            const opacity = 1 - (distance / 140);
                            context.beginPath();
                            context.strokeStyle = 'rgba(0, 212, 180, ' + (opacity * 0.18) + ')';
                            context.lineWidth = 0.6 + opacity * 1.2;
                            context.moveTo(particle.x, particle.y);
                            context.lineTo(other.x, other.y);
                            context.stroke();
                        }
                    }
                }

                for (let index = 0; index < comets.length; index += 1) {
                    const comet = comets[index];
                    updateComet(comet, index);
                    const tailX = comet.x - (comet.vx / Math.abs(comet.vx || 1)) * comet.length;
                    const tailY = comet.y - comet.vy * (comet.length / Math.max(Math.abs(comet.vx), 1));
                    const trail = context.createLinearGradient(comet.x, comet.y, tailX, tailY);
                    trail.addColorStop(0, comet.color);
                    trail.addColorStop(0.35, 'rgba(255,255,255,0.35)');
                    trail.addColorStop(1, 'rgba(255,255,255,0)');
                    context.beginPath();
                    context.strokeStyle = trail;
                    context.lineWidth = 2.2;
                    context.moveTo(comet.x, comet.y);
                    context.lineTo(tailX, tailY);
                    context.stroke();

                    context.beginPath();
                    context.fillStyle = 'rgba(255,255,255,0.95)';
                    context.arc(comet.x, comet.y, 2.4, 0, Math.PI * 2);
                    context.fill();
                }

                requestAnimationFrame(drawScene);
            }

            window.addEventListener('mousemove', (event) => {
                pointer.x = event.clientX;
                pointer.y = event.clientY;
                pointer.active = true;
            });

            window.addEventListener('mouseout', () => {
                pointer.active = false;
                pointer.x = null;
                pointer.y = null;
            });

            window.addEventListener('resize', resizeCanvas);
            resizeCanvas();
            updateParticleColors('dark');
            requestAnimationFrame(drawScene);
        })();
    </script>
</body>
</html>