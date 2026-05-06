<?php
/**
 * Template part los estilos de la pagina
 */
?>

<script>
    // Configuración de Tailwind
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#137fec",
                    "background-light": "#f6f7f8",
                    "background-dark": "#101922",
                },
                fontFamily: {
                    "display": ["Manrope", "sans-serif"]
                },
                borderRadius: { "DEFAULT": "0.4rem", "lg": "0.75rem", "xl": "1rem", "2xl": "1.5rem", "full": "9999px" },
            },
        },
    }

    // Lógica del menú hamburguesa
    const btn = document.getElementById('mobile-menu-button');
    const menu = document.getElementById('mobile-menu');

    if (btn && menu) {
        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
            btn.innerText = menu.classList.contains('hidden') ? 'menu' : 'close';
        });
    }
</script>