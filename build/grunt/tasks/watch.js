module.exports = function(grunt) {

	// Watch for changes
	grunt.config('watch', {
		config: {
			files: 'Gruntfile.js'
		},
		// Watch for the frontend scss files changes
		dist: {
			files: [
				'<%= cfg.paths.root %>assets/scss/**/*.scss',

				// if needed, exclude files to avoid delaying watch task
				// example: '!assets/css/scss/file.scss'
			],
			tasks:   ['sass:dist', 'postcss:dist', 'bs-inject-css'],
			options: {
				spawn: false
			}
		},
		// Watch for the js files changes
		scripts: {
			files: [
				'<%= cfg.paths.root %>assets/js/*/**/*.js'
			],
			tasks:   ['concat', 'bs-reload'],
			options: {
				spawn: false
			}
		},

		// Watch for the php files changes to trigger browsersync reload
		php: {
			files: [
				'<%= cfg.paths.root %>**/*.php',
				'<%= cfg.paths.root %>*.php',
				'<%= cfg.paths.framework %>**/*.php',
			],
			tasks:   ['bs-reload'],
			options: {
				spawn: false
			}
		}
	});
	grunt.loadNpmTasks('grunt-contrib-watch');

};

