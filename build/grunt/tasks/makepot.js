/* global module */
module.exports = function(grunt) {

	grunt.config('makepot', {
		target: {
			options: {
				cwd: '<%= cfg.paths.root %>',
				domainPath: 'languages/',
				potFilename: '<%= cfg.i18n.potFilename %>.pot',
				mainFile: '<%= cfg.i18n.mainFile %>.php',
				exclude: [
					'assets/',
					'bin/',
					'build/',
					'lib/',
					'languages/',
					'node_modules',
					'release/',
					'svn/',
					'tests/',
					'tmp',
					'vendor',
				],
				potComments: '',
				potHeaders: {
					'poedit':                true,
					'x-poedit-keywordslist': true,
					'language':              'en_US',
					'report-msgid-bugs-to':  '<%= cfg.i18n.support %>',
					'last-translator':       '<%= cfg.i18n.author %>',
					'language-Team':         '<%= cfg.i18n.author %>',
				},
				type: 'wp-plugin',
				updateTimestamp: true,
				updatePoFiles: true,
				processPot: null,
			},
		},
	});

	grunt.loadNpmTasks('grunt-wp-i18n');
}
