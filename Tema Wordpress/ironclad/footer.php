<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package IronClad
 */

?>
<footer class="bg-slate-50 dark:bg-[#15202b] border-t border-slate-200 dark:border-slate-800 py-12">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		<div class="flex flex-col md:flex-row justify-between items-center gap-8">
			<div class="flex items-center gap-2">
				<div
					class="bg-primary/20 p-2 rounded-lg flex items-center justify-center text-primary material-symbols-outlined">
					account_balance
				</div>
				<span class="text-slate-900 dark:text-white text-lg font-bold">IronClad</span>
			</div>
			<div class="flex gap-8">
				<a class="text-sm text-slate-500 hover:text-primary transition-colors" href="#">Terms</a>
				<a class="text-sm text-slate-500 hover:text-primary transition-colors" href="#">Privacy</a>
				<a class="text-sm text-slate-500 hover:text-primary transition-colors" href="#">Contact</a>
			</div>
			<p class="text-xs text-slate-500">© 2026 IronClad.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>

</html>