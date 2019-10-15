// Minify CSS files into .min.css
module.exports = function( grunt ) {

	grunt.config( 'cssmin', {
		release: {
			expand: true,
			src: [
				'assets/**/*'
			],
			dest: 'dist/assets/',
			ext: '.min.css',
		},
	} );

	grunt.loadNpmTasks('grunt-contrib-cssmin');

};
