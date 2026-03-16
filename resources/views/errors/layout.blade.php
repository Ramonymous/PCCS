<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body {
            height: 100%;
            overflow: hidden; /* prevent scrollbars; keep the layout perfectly centered */
        }
        /* Matrix Rain Canvas */
        #matrixCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.25;
            pointer-events: none;
        }
        
        /* Particle System */
        #particleCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.4;
            pointer-events: none;
        }
        
        .content-wrapper {
            position: relative;
            z-index: 1;
        }
        
        /* Cyberpunk Grid */
        .cyber-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(90deg, rgba(0, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(rgba(0, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 0;
            pointer-events: none;
        }
        
        /* Scan Line Effect */
        @keyframes scanline {
            0% {
                transform: translateY(-100%);
            }
            100% {
                transform: translateY(100vh);
            }
        }
        
        .scanline {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(transparent, rgba(0, 255, 255, 0.8), transparent);
            animation: scanline 4s linear infinite;
            z-index: 2;
            pointer-events: none;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        @keyframes glitchIntense {
            0%, 100% {
                text-shadow: 
                    3px 3px 0px rgba(255, 0, 255, 0.8), 
                    -3px -3px 0px rgba(0, 255, 255, 0.8),
                    0 0 20px rgba(255, 0, 0, 0.5);
                transform: translate(0);
            }
            10% {
                text-shadow: 
                    -3px 3px 0px rgba(0, 255, 255, 0.8), 
                    3px -3px 0px rgba(255, 0, 255, 0.8),
                    0 0 20px rgba(0, 255, 0, 0.5);
                transform: translate(-2px, 2px);
            }
            20% {
                text-shadow: 
                    3px -3px 0px rgba(255, 255, 0, 0.8), 
                    -3px 3px 0px rgba(255, 0, 0, 0.8),
                    0 0 20px rgba(0, 0, 255, 0.5);
                transform: translate(2px, -2px);
            }
            30% {
                text-shadow: 
                    -3px -3px 0px rgba(0, 255, 0, 0.8), 
                    3px 3px 0px rgba(255, 0, 0, 0.8),
                    0 0 20px rgba(255, 0, 255, 0.5);
                transform: translate(-2px, -2px);
            }
            50% {
                text-shadow: 
                    3px 3px 0px rgba(255, 0, 255, 0.8), 
                    -3px -3px 0px rgba(0, 255, 255, 0.8),
                    0 0 20px rgba(255, 255, 0, 0.5);
                transform: translate(0);
            }
        }
        
        @keyframes neonPulse {
            0%, 100% {
                box-shadow: 
                    0 0 10px rgba(0, 255, 255, 0.5),
                    0 0 20px rgba(0, 255, 255, 0.3),
                    0 0 30px rgba(0, 255, 255, 0.2),
                    inset 0 0 10px rgba(0, 255, 255, 0.1);
            }
            50% {
                box-shadow: 
                    0 0 20px rgba(0, 255, 255, 0.8),
                    0 0 40px rgba(0, 255, 255, 0.5),
                    0 0 60px rgba(0, 255, 255, 0.3),
                    inset 0 0 20px rgba(0, 255, 255, 0.2);
            }
        }
        
        @keyframes hologramFlicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
            75% { opacity: 0.9; }
        }
        
        .error-code {
            animation: fadeInDown 0.6s ease-out, glitchIntense 3s infinite 1s;
            filter: drop-shadow(0 0 20px rgba(255, 0, 0, 0.8));
        }
        
        .error-icon {
            animation: fadeInDown 0.8s ease-out 0.2s both, float 3s ease-in-out infinite;
        }
        
        .error-message {
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }
        
        .error-content {
            animation: fadeIn 1s ease-out 0.6s both;
        }
        
        .error-actions {
            animation: fadeInUp 1s ease-out 0.8s both;
        }
        
        .neon-card {
            background: rgba(10, 10, 30, 0.85);
            backdrop-filter: blur(10px) saturate(180%);
            border: 2px solid rgba(0, 255, 255, 0.3);
            animation: neonPulse 2s ease-in-out infinite, hologramFlicker 5s ease-in-out infinite;
        }
        
        .neon-btn {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid currentColor;
        }
        
        .neon-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .neon-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .neon-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px currentColor;
        }
        
        .whatsapp-btn {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .whatsapp-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }
        
        .whatsapp-btn:hover::after {
            width: 300px;
            height: 300px;
        }
        
        .whatsapp-btn:hover {
            transform: scale(1.08) rotate(-2deg);
            box-shadow: 0 15px 35px rgba(37, 211, 102, 0.5);
        }
        
        /* Glowing text */
        .glow-text {
            text-shadow: 
                0 0 10px rgba(0, 255, 255, 0.8),
                0 0 20px rgba(0, 255, 255, 0.6),
                0 0 30px rgba(0, 255, 255, 0.4);
        }
        
        /* Binary rain glow */
        .binary-glow {
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.8);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900">
    {{-- Cyberpunk Grid Background --}}
    <div class="cyber-grid"></div>
    
    {{-- Matrix Rain Background --}}
    <canvas id="matrixCanvas"></canvas>
    
    {{-- Particle System --}}
    <canvas id="particleCanvas"></canvas>
    
    {{-- Scan Line Effect --}}
    <div class="scanline"></div>
    
    <div class="content-wrapper flex items-center justify-center h-screen px-4 py-6">
        <div class="w-full max-w-2xl">
            <div class="neon-card rounded-2xl p-8 shadow-2xl">
                <div class="text-center">
                    {{-- Error Code --}}
                    <div class="mb-6 error-code">
                        <h1 class="text-7xl md:text-9xl font-black text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-pink-500 to-purple-500">
                            @yield('code')
                        </h1>
                    </div>

                    {{-- Error Icon --}}
                    <div class="mb-6 error-icon">
                        @yield('icon')
                    </div>

                    {{-- Error Message --}}
                    <div class="mb-8 error-message">
                        <h2 class="text-3xl md:text-4xl font-bold mb-4 text-white glow-text">
                            @yield('title')
                        </h2>
                        <p class="text-lg md:text-xl text-cyan-300">
                            @yield('message')
                        </p>
                    </div>

                    {{-- Additional Content --}}
                    <div class="error-content mb-8 text-gray-300">
                        @yield('content')
                    </div>

                    {{-- Action Buttons --}}
                    @php($errorCode = trim($__env->yieldContent('code')))
                    <div class="flex flex-col sm:flex-row gap-4 justify-center error-actions">
                        <x-button 
                            label="Go Back" 
                            icon="o-arrow-left" 
                            class="neon-btn bg-cyan-500 hover:bg-cyan-600 text-white border-cyan-400 font-bold px-6 py-3"
                            onclick="history.back()"
                        />
                        <x-button 
                            label="Dashboard" 
                            icon="o-home" 
                            link="{{ route('dashboard') }}"
                            class="neon-btn bg-purple-500 hover:bg-purple-600 text-white border-purple-400 font-bold px-6 py-3"
                        />
                        <a 
                            href="https://wa.me/6285160185678?text={{ rawurlencode('Hi, I encountered an error (' . ($errorCode ?: 'N/A') . ') on ' . config('app.name')) }}" 
                            target="_blank"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#25D366] text-white text-sm font-bold rounded-lg whatsapp-btn"
                        >
                            <svg class="w-5 h-5 relative z-10" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                            <span class="relative z-10">Contact Support</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="text-center mt-2 text-cyan-400 text-sm animate-fadeIn glow-text">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script>
        // Matrix Rain Effect with Enhanced Colors
        const canvas = document.getElementById('matrixCanvas');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン';
        const characters = matrix.split('');

        const fontSize = 16;
        const columns = canvas.width / fontSize;
        const drops = [];
        
        for (let i = 0; i < columns; i++) {
            drops[i] = Math.floor(Math.random() * canvas.height / fontSize);
        }

        function draw() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.04)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.font = fontSize + 'px monospace';

            for (let i = 0; i < drops.length; i++) {
                const text = characters[Math.floor(Math.random() * characters.length)];
                
                // Gradient color for matrix rain
                const gradient = ctx.createLinearGradient(0, drops[i] * fontSize - 20, 0, drops[i] * fontSize);
                gradient.addColorStop(0, 'rgba(0, 255, 255, 0)');
                gradient.addColorStop(0.5, 'rgba(0, 255, 255, 0.8)');
                gradient.addColorStop(1, 'rgba(0, 255, 255, 1)');
                
                ctx.fillStyle = gradient;
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }

        setInterval(draw, 35);

        // Particle System
        const particleCanvas = document.getElementById('particleCanvas');
        const pCtx = particleCanvas.getContext('2d');
        
        particleCanvas.width = window.innerWidth;
        particleCanvas.height = window.innerHeight;

        class Particle {
            constructor() {
                this.x = Math.random() * particleCanvas.width;
                this.y = Math.random() * particleCanvas.height;
                this.size = Math.random() * 2 + 1;
                this.speedX = Math.random() * 1 - 0.5;
                this.speedY = Math.random() * 1 - 0.5;
                this.color = `hsl(${Math.random() * 60 + 180}, 100%, 50%)`;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                if (this.x > particleCanvas.width || this.x < 0) this.speedX *= -1;
                if (this.y > particleCanvas.height || this.y < 0) this.speedY *= -1;
            }

            draw() {
                pCtx.fillStyle = this.color;
                pCtx.shadowBlur = 10;
                pCtx.shadowColor = this.color;
                pCtx.beginPath();
                pCtx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                pCtx.fill();
            }
        }

        const particles = [];
        for (let i = 0; i < 100; i++) {
            particles.push(new Particle());
        }

        function animateParticles() {
            pCtx.clearRect(0, 0, particleCanvas.width, particleCanvas.height);
            
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });

            // Connect particles
            particles.forEach((p1, i) => {
                particles.slice(i + 1).forEach(p2 => {
                    const dx = p1.x - p2.x;
                    const dy = p1.y - p2.y;
                    const distance = Math.sqrt(dx * dx + dy * dy);

                    if (distance < 100) {
                        pCtx.strokeStyle = `rgba(0, 255, 255, ${1 - distance / 100})`;
                        pCtx.lineWidth = 0.5;
                        pCtx.beginPath();
                        pCtx.moveTo(p1.x, p1.y);
                        pCtx.lineTo(p2.x, p2.y);
                        pCtx.stroke();
                    }
                });
            });

            requestAnimationFrame(animateParticles);
        }

        animateParticles();

        // Resize handler
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            particleCanvas.width = window.innerWidth;
            particleCanvas.height = window.innerHeight;
        });
    </script>
</body>
</html>