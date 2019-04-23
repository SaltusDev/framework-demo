module.exports = function(grunt) {

	const sass = require('node-sass');
	grunt.config('sass', {

		dist: {
			options: {
				implementation: sass,
				outputStyle:    'expanded',
			},
			files: [{
				expand: true,
				src:    ['lib/Module/**/*.scss'],
				ext:    '.css'
			}]
		}
	});

	grunt.loadNpmTasks('grunt-sass');

};