module.exports = function(grunt) {

	// Post process css for production: add prefixes and rem fallbacks,
	grunt.config('postcss', {

		dist: {
			options: {
				processors: [
					require('autoprefixer')({browsers: 'last 3 versions'}),
					require('cssnano')() // minify the result
				]
			},
			files: [{
				expand: true,
				src:    ['lib/Module/**/*.css'],
				ext:    '.css'
			}]
		}
	});

	grunt.loadNpmTasks('grunt-postcss');

};