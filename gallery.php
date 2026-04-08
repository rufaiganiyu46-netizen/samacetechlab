<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery | SAMACE TECH LAB NURSERY AND PRIMARY SCHOOL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;800&family=Nunito:wght@400;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#1A3C6E; --accent:#F5A623; --cream:#FDF6EC; --text:#233041; --soft:#5b6574; --white:#fff; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Nunito',sans-serif; color:var(--text); background:linear-gradient(180deg,#fffdf8 0%,var(--cream) 100%); position:relative; min-height:100vh; overflow-x:hidden; }
        #particles-container { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        @keyframes floatDot { 0% { transform:translate(0,0) scale(1); opacity:.35; } 33% { transform:translate(10px,-15px) scale(1.2); opacity:.8; } 66% { transform:translate(-8px,8px) scale(.85); opacity:.25; } 100% { transform:translate(15px,-20px) scale(1); opacity:.45; } }
        header, main, footer { position:relative; z-index:1; }
        .container { width:min(100% - 32px, 1100px); margin:0 auto; }
        .header { position:sticky; top:0; background:rgba(253,246,236,.9); backdrop-filter:blur(14px); border-bottom:1px solid rgba(26,60,110,.08); }
        .nav { display:flex; align-items:center; justify-content:space-between; min-height:78px; gap:20px; }
        .brand { display:flex; align-items:center; gap:14px; color:var(--primary); font-weight:800; }
        .brand-mark { width:64px; height:64px; border-radius:20px; background:linear-gradient(135deg, rgba(26,60,110,.08), rgba(45,127,166,.12)); box-shadow:0 14px 24px rgba(26,60,110,.12); padding:10px; flex-shrink:0; }
        .brand-mark img { width:100%; height:100%; object-fit:contain; }
        .brand-text strong { display:block; font-family:'Cinzel','Playfair Display',serif; font-size:1.2rem; line-height:1.15; font-weight:800; letter-spacing:.02em; }
        .brand-text span { display:block; color:var(--soft); font-size:.92rem; }
        .nav-links { display:flex; gap:10px; flex-wrap:wrap; }
        .nav-links a { padding:10px 14px; border-radius:999px; font-weight:800; color:var(--primary); }
        .nav-links a.active, .nav-links a:hover { background:rgba(26,60,110,.08); }
        .hero { padding:68px 0 28px; }
        .hero-box { border-radius:30px; padding:42px; background:linear-gradient(135deg,#1A3C6E 0%, #F5A623 100%); color:var(--white); box-shadow:0 24px 48px rgba(19,45,83,.2); }
        h1, h2 { font-family:'Playfair Display',serif; }
        h1 { font-size:clamp(2.2rem,5vw,4rem); line-height:1.08; margin-bottom:14px; }
        .hero-box p { color:rgba(255,255,255,.9); max-width:760px; line-height:1.75; }
        .section { padding:34px 0 84px; }
        .grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px; }
        .tile { min-height:220px; border-radius:28px; padding:24px; background:linear-gradient(135deg,rgba(26,60,110,.95),rgba(45,127,166,.9)); color:var(--white); box-shadow:0 18px 34px rgba(22,38,70,.08); display:flex; flex-direction:column; justify-content:flex-end; }
        .tile:nth-child(2n) { background:linear-gradient(135deg,rgba(245,166,35,.92),rgba(255,202,98,.92)); color:#4b3000; }
        .tile:nth-child(3n) { background:linear-gradient(135deg,rgba(31,165,139,.92),rgba(79,195,171,.92)); }
        .tile strong { font-size:1.2rem; margin-bottom:8px; }
        .tile span { line-height:1.7; }
        @media (max-width: 900px) { .grid { grid-template-columns:1fr; } }
        @media (max-width: 768px) { .hero-box { padding:30px 22px; } .nav { flex-direction:column; align-items:flex-start; padding:14px 0; } }
    </style>
    <?php require_once __DIR__ . '/theme-shared.php'; ?>
</head>
<body>
    <div id="particles-container" aria-hidden="true"></div>
    <header class="header">
        <div class="container nav">
            <div class="brand"><span class="brand-mark"><img src="assets/images/samace-logo.svg" alt="SAMACE TECH LAB logo"></span><span class="brand-text"><strong>SAMACE TECH LAB NURSERY AND PRIMARY SCHOOL</strong><span>Lagos, Nigeria</span></span></div>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="about.php">About</a>
                <a href="academics.php">Academics</a>
                <a href="contact.php">Contact</a>
            </nav>
        </div>
    </header>
    <main>
        <section class="hero">
            <div class="container hero-box">
                <h1>School life in a joyful and inspiring environment</h1>
                <p>Explore the spirit of SAMACE TECH LAB through classroom moments, activities, celebrations, and a welcoming school atmosphere for children and families.</p>
            </div>
        </section>
        <section class="section">
            <div class="container grid">
                <article class="tile"><strong>Classroom Learning</strong><span>Interactive lessons and active participation across nursery and primary levels.</span></article>
                <article class="tile"><strong>School Events</strong><span>Celebrations, assemblies, and community moments that bring learners together.</span></article>
                <article class="tile"><strong>Creative Activities</strong><span>Art, music, storytelling, and expression as part of a rounded school experience.</span></article>
                <article class="tile"><strong>Reading Time</strong><span>Strong literacy habits nurtured through guided reading and quiet learning moments.</span></article>
                <article class="tile"><strong>Technology Corner</strong><span>Technology-aware learning spaces designed to introduce pupils to modern tools.</span></article>
                <article class="tile"><strong>Happy Community</strong><span>A safe, warm, and welcoming Nigerian school environment in Lagos.</span></article>
            </div>
        </section>
    </main>
    <script>
        (function () {
            const container = document.getElementById('particles-container');
            if (!container) {
                return;
            }

            const count = window.innerWidth < 768 ? 24 : 48;
            const colors = ['rgba(26,60,110,0.18)', 'rgba(45,127,166,0.16)', 'rgba(245,166,35,0.22)', 'rgba(255,255,255,0.22)'];

            for (let index = 0; index < count; index += 1) {
                const dot = document.createElement('span');
                const size = Math.random() * 3 + 1.5;
                dot.style.cssText = `position:absolute; width:${size}px; height:${size}px; border-radius:50%; background:${colors[Math.floor(Math.random() * colors.length)]}; top:${Math.random() * 100}%; left:${Math.random() * 100}%; animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;`;
                container.appendChild(dot);
            }
        })();
    </script>
</body>
</html>
