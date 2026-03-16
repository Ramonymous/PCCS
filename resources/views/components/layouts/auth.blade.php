<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        html, body { height: 100%; overflow: hidden; }
        /* Cyberpunk Grid Background */
        .cyber-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(90deg, rgba(0, 255, 0, 0.05) 1px, transparent 1px),
                linear-gradient(rgba(0, 255, 0, 0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 1;
        }

        /* Matrix Rain Canvas */
        #authMatrixCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.12;
            pointer-events: none;
            z-index: 1;
        }

        /* Animated Gradient Orbs */
        @keyframes float {
            0%, 100% { 
                transform: translate(0, 0) scale(1);
                opacity: 0.6;
            }
            33% { 
                transform: translate(50px, -80px) scale(1.2);
                opacity: 0.8;
            }
            66% { 
                transform: translate(-40px, 40px) scale(0.9);
                opacity: 0.4;
            }
        }

        @keyframes pulse {
            0%, 100% { 
                transform: scale(1);
                opacity: 0.5;
            }
            50% { 
                transform: scale(1.1);
                opacity: 0.7;
            }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: float 10s infinite ease-in-out;
            mix-blend-mode: screen;
        }

        .orb-1 {
            top: -10%;
            right: -5%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.5), transparent);
            animation-delay: 0s;
        }

        .orb-2 {
            bottom: -10%;
            left: -5%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.5), transparent);
            animation-delay: 2s;
        }

        .orb-3 {
            top: 30%;
            left: 50%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.5), transparent);
            animation-delay: 4s;
        }

        .orb-4 {
            top: 10%;
            left: 10%;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.4), transparent);
            animation-delay: 1s;
        }

        /* Scan Line Effect */
        @keyframes scanline {
            0% { transform: translateY(-100%); }
            100% { transform: translateY(100vh); }
        }

        .scanline {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(transparent, rgba(0, 255, 0, 0.7), transparent);
            animation: scanline 6s linear infinite;
            z-index: 3;
            pointer-events: none;
        }

        /* Glowing Particles */
        #particleCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
            opacity: 0.3;
        }

        /* Content Container Enhancement */
        .auth-container {
            position: relative;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        /* Neon Border Animation */
        @keyframes neonBorder {
            0%, 100% {
                box-shadow: 
                    0 0 5px rgba(0, 255, 255, 0.5),
                    0 0 10px rgba(0, 255, 255, 0.3),
                    0 0 20px rgba(0, 255, 255, 0.2),
                    inset 0 0 5px rgba(0, 255, 255, 0.1);
            }
            50% {
                box-shadow: 
                    0 0 10px rgba(139, 92, 246, 0.5),
                    0 0 20px rgba(139, 92, 246, 0.3),
                    0 0 40px rgba(139, 92, 246, 0.2),
                    inset 0 0 10px rgba(139, 92, 246, 0.1);
            }
        }

        .neon-border {
            animation: neonBorder 3s ease-in-out infinite;
        }

        /* Hologram Effect */
        @keyframes hologram {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.85; }
        }

        .hologram {
            animation: hologram 4s ease-in-out infinite;
        }

        /* Digital Noise Effect */
        @keyframes noise {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-2px, -2px); }
            20% { transform: translate(2px, 2px); }
            30% { transform: translate(-2px, 2px); }
            40% { transform: translate(2px, -2px); }
            50% { transform: translate(-2px, -2px); }
            60% { transform: translate(2px, 2px); }
            70% { transform: translate(-2px, 2px); }
            80% { transform: translate(2px, -2px); }
            90% { transform: translate(-2px, -2px); }
        }

        .digital-noise {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                0deg,
                rgba(0, 0, 0, 0.1) 0px,
                transparent 1px,
                transparent 2px,
                rgba(0, 0, 0, 0.1) 3px
            );
            opacity: 0.02;
            pointer-events: none;
            z-index: 2;
            animation: noise 0.2s infinite;
        }

        /* Glow Text */
    .glow-text {
            text-shadow: 
        0 0 10px rgba(0, 255, 0, 0.8),
        0 0 20px rgba(0, 255, 0, 0.5),
        0 0 30px rgba(0, 255, 0, 0.3);
        }

        /* Floating Animation for Logo/Brand */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }

        .float-slow {
            animation: floatSlow 4s ease-in-out infinite;
        }

        /* Hexagon Pattern */
        .hex-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none; /* hide non-green accents */
        }

        /* Hide orbs for cleaner green hack style */
        .orb { display: none !important; }

        /* Typed text overlay */
        .typed-container {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            pointer-events: none;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            color: #00ff7f;
            text-shadow: 0 0 8px rgba(0,255,0,0.7);
            opacity: 0.9;
            white-space: nowrap;
            user-select: none;
        }
        .typed-caret { display: inline-block; width: 8px; background: #00ff7f; margin-left: 4px; animation: caret-blink 1s step-end infinite; box-shadow: 0 0 6px rgba(0,255,0,0.8); }
        @keyframes caret-blink { 50% { opacity: 0; } }
    </style>
</head>
<body class="h-full font-sans antialiased bg-gradient-to-b from-black via-[#001a00] to-black overflow-hidden">
    {{-- Cyberpunk Grid --}}
    <div class="cyber-grid"></div>

    {{-- Hexagon Pattern (hidden for green style) --}}
    <div class="hex-pattern"></div>

    {{-- Matrix Rain --}}
    <canvas id="authMatrixCanvas"></canvas>

    {{-- Particle System --}}
    <canvas id="particleCanvas"></canvas>

    {{-- Digital Noise --}}
    <div class="digital-noise"></div>

    {{-- Scan Line --}}
    <div class="scanline"></div>

    {{-- Animated Gradient Orbs (disabled for green style) --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0"></div>

    {{-- Content Container --}}
    <div class="auth-container h-screen flex items-center justify-center p-4">
        <div class="hologram">
            {{ $slot }}
        </div>
    </div>

    {{-- Typed text: Ramonymous --}}
    <div class="typed-container" aria-hidden="true">
        <span id="typedText">R</span><span class="typed-caret"></span>
    </div>

    <script>
        // Respect user's motion preferences
        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Matrix Rain Effect
        const canvas = document.getElementById('authMatrixCanvas');
        const ctx = canvas.getContext('2d');
        
        function sizeCanvas(c) {
            const dpr = Math.min(window.devicePixelRatio || 1, 1.5); // cap DPR for perf
            c.width = Math.floor(window.innerWidth * dpr);
            c.height = Math.floor(window.innerHeight * dpr);
            const context = c.getContext('2d');
            context.setTransform(dpr, 0, 0, dpr, 0, 0);
        }

        sizeCanvas(canvas);
        
    const chars = '01';
        const fontSize = 14;
        let columns = Math.floor(window.innerWidth / fontSize);
        let drops = Array(columns).fill(0).map(() => Math.random() * window.innerHeight / fontSize);
        
        function drawMatrix() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.font = fontSize + 'px monospace';
            
            for (let i = 0; i < drops.length; i++) {
                const char = chars[Math.floor(Math.random() * chars.length)];
                const x = i * fontSize;
                const y = drops[i] * fontSize;
                
                const gradient = ctx.createLinearGradient(0, y - 20, 0, y);
                gradient.addColorStop(0, 'rgba(0, 255, 0, 0)');
                gradient.addColorStop(0.5, 'rgba(0, 255, 0, 0.6)');
                gradient.addColorStop(1, 'rgba(0, 255, 0, 1)');
                
                ctx.fillStyle = gradient;
                ctx.fillText(char, x, y);
                
                if (y > canvas.height && Math.random() > 0.98) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }

        let matrixIntervalId = null;
        function startMatrix() {
            if (matrixIntervalId !== null) return;
            matrixIntervalId = setInterval(drawMatrix, 60);
        }
        function stopMatrix() {
            if (matrixIntervalId !== null) {
                clearInterval(matrixIntervalId);
                matrixIntervalId = null;
            }
        }
        if (!reduceMotion) {
            startMatrix();
        } else {
            drawMatrix();
        }

        // Particle System
        const particleCanvas = document.getElementById('particleCanvas');
        const pCtx = particleCanvas.getContext('2d');

        function sizeParticleCanvas() {
            const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
            particleCanvas.width = Math.floor(window.innerWidth * dpr);
            particleCanvas.height = Math.floor(window.innerHeight * dpr);
            pCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        sizeParticleCanvas();

        class Particle {
            constructor() {
                this.x = Math.random() * window.innerWidth;
                this.y = Math.random() * window.innerHeight;
                this.size = Math.random() * 2;
                this.speedX = (Math.random() - 0.5) * 0.5;
                this.speedY = (Math.random() - 0.5) * 0.5;
                this.hue = Math.random() * 60 + 180; // Cyan to purple range
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                if (this.x < 0 || this.x > window.innerWidth) this.speedX *= -1;
                if (this.y < 0 || this.y > window.innerHeight) this.speedY *= -1;
            }

            draw() {
                pCtx.fillStyle = `hsla(${this.hue}, 100%, 60%, 0.8)`;
                pCtx.shadowBlur = 8;
                pCtx.shadowColor = `hsl(${this.hue}, 100%, 60%)`;
                pCtx.beginPath();
                pCtx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                pCtx.fill();
            }
        }

    const PARTICLE_COUNT = reduceMotion ? 20 : 60;
        const particles = Array(PARTICLE_COUNT).fill().map(() => new Particle());

        function animateParticles() {
            pCtx.clearRect(0, 0, particleCanvas.width, particleCanvas.height);
            
            particles.forEach(p => {
                p.update();
                p.draw();
            });

            // Connect nearby particles
            if (!reduceMotion) {
                particles.forEach((p1, i) => {
                    particles.slice(i + 1).forEach(p2 => {
                        const dx = p1.x - p2.x;
                        const dy = p1.y - p2.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);

                        if (dist < 120) {
                            pCtx.strokeStyle = `rgba(0, 255, 0, ${(1 - dist / 120) * 0.25})`;
                            pCtx.lineWidth = 0.5;
                            pCtx.beginPath();
                            pCtx.moveTo(p1.x, p1.y);
                            pCtx.lineTo(p2.x, p2.y);
                            pCtx.stroke();
                        }
                    });
                });
            }

            if (!document.hidden) requestAnimationFrame(animateParticles);
        }

        if (!reduceMotion) {
            animateParticles();
        }

        // Resize handler
        window.addEventListener('resize', () => {
            sizeCanvas(canvas);
            sizeParticleCanvas();
            columns = Math.floor(window.innerWidth / fontSize);
            drops = Array(columns).fill(0).map(() => Math.random() * window.innerHeight / fontSize);
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopMatrix();
            } else if (!reduceMotion) {
                startMatrix();
                animateParticles();
            }
        });

        // Typed "Ramonymous" effect
        (function typeRamonymous(){
            const target = document.getElementById('typedText');
            if (!target) return;
            const text = 'Ramonymous';
            let idx = 1;
            function tick(){
                target.textContent = text.slice(0, idx);
                idx = idx < text.length ? idx + 1 : 1; // loop typing
                setTimeout(tick, reduceMotion ? 500 : 200);
            }
            tick();
        })();
    </script>
</body>
</html>