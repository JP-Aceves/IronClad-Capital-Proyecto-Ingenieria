<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package IronClad
 */

?>

<?php get_template_part('template-parts/content', 'head'); ?>

<body <?php body_class('bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 font-display selection:bg-primary/30'); ?>>
    <header class="sticky top-0 z-50 w-full border-b border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-[#101922]/80 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-20 items-center justify-between">
                <div class="flex items-center gap-8">
                    <a class="flex items-center gap-2" href="<?php echo esc_url(home_url('/')); ?>">
                        <div class="bg-primary/20 p-2 rounded-lg flex items-center justify-center text-primary material-symbols-outlined text-2xl">
                            account_balance
                        </div>
                        <span class="text-slate-900 dark:text-white text-xl font-extrabold tracking-tight">IronClad</span>
                    </a>
                    
                    <nav class="hidden md:flex items-center gap-6">
                        <a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary transition-colors" href="#">Features</a>
                        <a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary transition-colors" href="#">Markets</a>
                        <a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary transition-colors" href="#">Pricing</a>
                        <a class="text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-primary dark:hover:text-primary transition-colors" href="#">About</a>
                    </nav>
                </div>

                <div class="flex items-center gap-4">
                    <button class="border-none hidden sm:block text-sm font-semibold text-slate-700 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors">Log In</button>
                    <button class="!border-none h-11 px-6 rounded-lg bg-primary text-white text-sm font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                        Get Started
                    </button>
                    
                    <button id="mobile-menu-button" class="flex md:hidden text-slate-600 dark:text-slate-300 p-2 material-symbols-outlined text-3xl">
                        menu
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-background-dark">
            <nav class="flex flex-col p-4 space-y-4">
                <a class="text-base font-medium text-slate-600 dark:text-slate-400 hover:text-primary" href="#">Features</a>
                <a class="text-base font-medium text-slate-600 dark:text-slate-400 hover:text-primary" href="#">Markets</a>
                <a class="text-base font-medium text-slate-600 dark:text-slate-400 hover:text-primary" href="#">Pricing</a>
                <a class="text-base font-medium text-slate-600 dark:text-slate-400 hover:text-primary" href="#">About</a>
                <hr class="border-slate-100 dark:border-slate-800">
                <button class="text-left text-base font-medium text-slate-600 dark:text-slate-400">Log In</button>
            </nav>
        </div>
    </header>
    <?php get_template_part('template-parts/content', 'styles-header'); ?>