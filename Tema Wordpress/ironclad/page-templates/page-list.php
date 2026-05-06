<?php
/**
 * Template Name: Lista Monedas
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}
?>
<?php get_template_part('template-parts/content', 'head'); ?>
<body <?php body_class('bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display selection:bg-primary/30'); ?>>
    <?php get_template_part('template-parts/content', 'styles-header'); ?>
    <div class="relative flex h-auto min-h-screen w-full flex-col overflow-x-hidden">
        <div class="layout-container flex h-full grow flex-col md:flex-row">
            <!-- SideNavBar -->
            <div
                class="flex h-full min-h-screen w-[280px] flex-col justify-between border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-[#15202b] p-4 hidden md:flex shrink-0">
                <div class="flex flex-col gap-4">
                    <div class="flex gap-3 mb-6 items-center">
                        <div
                            class="bg-primary/20 bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10 flex items-center justify-center text-primary material-symbols-outlined">
                            account_balance
                        </div>
                        <div class="flex flex-col">
                            <h1 class="text-slate-900 dark:text-white text-base font-bold leading-normal">IronClad</h1>
                            <p class="text-slate-500 dark:text-slate-400 text-sm font-normal leading-normal">Cuenta Pro
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            href="#">
                            <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">dashboard</span>
                            <p class="text-slate-700 dark:text-slate-300 text-sm font-medium leading-normal">Dashboard
                            </p>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/10 text-primary" href="#">
                            <span class="material-symbols-outlined text-primary"
                                style="font-variation-settings: 'FILL' 1;">trending_up</span>
                            <p class="text-primary text-sm font-bold leading-normal">Mercados</p>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            href="#">
                            <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">pie_chart</span>
                            <p class="text-slate-700 dark:text-slate-300 text-sm font-medium leading-normal">Porfolio
                            </p>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            href="#">
                            <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">show_chart</span>
                            <p class="text-slate-700 dark:text-slate-300 text-sm font-medium leading-normal">Señales</p>
                        </a>
                        <a class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors mt-auto"
                            href="#">
                            <span class="material-symbols-outlined text-slate-500 dark:text-slate-400">settings</span>
                            <p class="text-slate-700 dark:text-slate-300 text-sm font-medium leading-normal">Ajustes
                            </p>
                        </a>
                    </div>
                </div>
                <div class="mt-8">
                    <div
                        class="p-4 rounded-xl bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                        <p class="text-sm font-medium mb-2">Valor Porfolio</p>
                        <h3 class="text-xl font-bold mb-1">$124,560.00</h3>
                        <p class="text-sm text-green-500 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[16px]">arrow_upward</span>
                            +2.4% Hoy
                        </p>
                    </div>
                </div>
            </div>
            <!-- Main Content -->
            <div class="flex-1 flex flex-col w-full max-w-7xl mx-auto px-4 md:px-8 py-6">
                <?php echo do_shortcode('[market_assets_list]'); ?>
            </div>
        </div>
    </div>
</body>

</html>