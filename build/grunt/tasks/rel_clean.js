module.exports = function( grunt ) {

	grunt.config( 'clean', {

		release: {
			expand: true,
			src: ['dist']
		}
	} );

	grunt.loadNpmTasks( 'grunt-contrib-clean' );

};
