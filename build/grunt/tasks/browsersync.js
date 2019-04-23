module.exports = function(grunt) {
	// Init BrowserSync manually

	var browserSync = require('browser-sync');
	grunt.registerTask('bs-init', function () {
		browserSync({
			open:       'external',
			watchTask:  true,
			logPrefix:  grunt.template.process('<%= pkg.name %>'),
			timestamps: true,
			proxy:      {
				target: grunt.template.process('<%= env.localurl %>')
			}
		}, function (err, bs) {

		});
	});
	// Inject CSS files to the browser
	grunt.registerTask('bs-inject-css', function () {
		browserSync.reload( '*.css' );
	});
	// Reload browser
	grunt.registerTask('bs-reload', function () {
		browserSync.reload([
			'<%= cfg.paths.root %>**/*.php',
			'<%= cfg.paths.root %>*.php',
			'<%= cfg.paths.framework %>**/*.php',
		]);
	});
};
