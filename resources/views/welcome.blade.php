<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SénFoot - Réservation de Futsal au Sénégal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .hero-gradient { background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900">

    <nav class="fixed w-full z-50 glass border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black text-green-700">SÉN<span class="text-orange-500">FOOT</span></span>
            </div>
            <div class="hidden md:flex gap-8 font-medium">
                <a href="#" class="hover:text-green-600">Terrains</a>
                <a href="#" class="hover:text-green-600">Pourquoi nous ?</a>
                <a href="#" class="hover:text-green-600">Tarifs</a>
            </div>
            <a href="#" class="bg-orange-500 text-white px-6 py-2 rounded-full font-bold hover:bg-orange-600 transition">Réserver</a>
        </div>
    </nav>

    <section class="hero-gradient h-screen flex items-center text-white pt-16">
        <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">
            <div>
                <span class="bg-green-600 text-xs font-bold uppercase px-3 py-1 rounded-full">Disponible à Dakar</span>
                <h1 class="text-5xl md:text-7xl font-extrabold mt-4 leading-tight">
                    Le Futsal au Sénégal passe au <span class="text-orange-500">Digital</span>
                </h1>
                <p class="text-lg text-gray-200 mt-6 mb-8">
                    Réservez votre terrain en 3 clics, payez en ligne via Orange Money ou Wave, et concentrez-vous sur le match.
                </p>
                <div class="flex flex-wrap gap-4">
                    <button class="bg-orange-500 hover:bg-orange-600 text-white px-8 py-4 rounded-xl font-bold flex items-center gap-2">
                        <i class="fas fa-futbol"></i> Trouver un terrain
                    </button>
                    <button class="bg-white/10 hover:bg-white/20 backdrop-blur-md text-white border border-white/30 px-8 py-4 rounded-xl font-bold">
                        Espace Propriétaire
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 bg-white border-b">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div>
                <p class="text-3xl font-black text-green-700">50+</p>
                <p class="text-gray-500 text-sm uppercase font-bold">Terrains</p>
            </div>
            <div>
                <p class="text-3xl font-black text-green-700">10k+</p>
                <p class="text-gray-500 text-sm uppercase font-bold">Joueurs</p>
            </div>
            <div>
                <p class="text-3xl font-black text-green-700">24/7</p>
                <p class="text-gray-500 text-sm uppercase font-bold">Réservation</p>
            </div>
            <div>
                <p class="text-3xl font-black text-green-700">0</p>
                <p class="text-gray-500 text-sm uppercase font-bold">Doubles réservations</p>
            </div>
        </div>
    </section>

    <section class="py-24 max-w-7xl mx-auto px-6">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold">Pourquoi choisir SénFoot ?</h2>
            <div class="w-20 h-1 bg-orange-500 mx-auto mt-4"></div>
        </div>

        <div class="grid md:grid-cols-3 gap-12">
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 hover:shadow-xl transition group">
                <div class="w-14 h-14 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-green-600 group-hover:text-white transition">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="text-xl font-bold mb-4">Simple & Rapide</h3>
                <p class="text-gray-600">Une interface intuitive pensée pour les passionnés de foot. Réservez en moins de 2 minutes.</p>
            </div>

            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 hover:shadow-xl transition group">
                <div class="w-14 h-14 bg-orange-100 text-orange-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-orange-600 group-hover:text-white transition">
                    <i class="fas fa-wallet"></i>
                </div>
                <h3 class="text-xl font-bold mb-4">Paiements Locaux</h3>
                <p class="text-gray-600">Intégration fluide de Wave, Orange Money et Free Money pour une sécurité totale.</p>
            </div>

            <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 hover:shadow-xl transition group">
                <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mb-6 text-2xl group-hover:bg-blue-600 group-hover:text-white transition">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="text-xl font-bold mb-4">Gestion Pro</h3>
                <p class="text-gray-500">Propriétaires, suivez vos revenus et votre taux d'occupation avec des statistiques précises.</p>
            </div>
        </div>
    </section>

    <section class="mb-24 px-6">
        <div class="max-w-7xl mx-auto bg-green-700 rounded-[3rem] p-12 text-center text-white relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-4xl font-bold mb-6">Prêt à entrer sur le terrain ?</h2>
                <p class="text-green-100 mb-10 max-w-xl mx-auto text-lg">Rejoignez la plus grande communauté de Futsal au Sénégal dès aujourd'hui.</p>
                <button class="bg-white text-green-700 px-10 py-4 rounded-full font-black text-lg hover:scale-105 transition">
                    TÉLÉCHARGER L'APP
                </button>
            </div>
            <div class="absolute top-0 right-0 opacity-10 transform translate-x-20 -translate-y-20 text-[200px]">
                <i class="fas fa-futbol"></i>
            </div>
        </div>
    </section>

    <footer class="bg-gray-900 text-gray-400 py-12 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
            <p>© 2026 SénFoot. Développé pour l'excellence sportive.</p>
            <div class="flex gap-6 text-xl">
                <a href="#" class="hover:text-white"><i class="fab fa-instagram"></i></a>
                <a href="#" class="hover:text-white"><i class="fab fa-facebook"></i></a>
                <a href="#" class="hover:text-white"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

</body>
</html>
