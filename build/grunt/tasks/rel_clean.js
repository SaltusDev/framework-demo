module.exports = function( grunt ) {

	grunt.config( 'clean', {

		dist: {
			cwd: '<%= cfg.paths.root %>/',
			expand: true,
			options: {
				force: false,
			},
			src: ['dist']
		}
	} );

	grunt.loadNpmTasks( 'grunt-contrib-clean' );

};
