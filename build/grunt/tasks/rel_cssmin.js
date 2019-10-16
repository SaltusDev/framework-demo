// Minify CSS files into .min.css
module.exports = function( grunt ) {

	grunt.config( 'cssmin', {
		release: {
			expand: true,
			cwd: '../',
			src: [
				'assets/css/*'
			],
			dest: 'dist/<%= pkg.name %>/',
			ext: '.min.css',
		},
	} );

	grunt.loadNpmTasks('grunt-contrib-cssmin');

};
