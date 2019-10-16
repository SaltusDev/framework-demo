module.exports = function( grunt ) {

	grunt.config( 'version', {
		releasePHP: {
			options: {
				prefix: ' * Version\\:\\s*'
			},
			src: [ '<%= cfg.paths.root %>/<%= cfg.i18n.mainFile %>.php' ],
		}
	} );

	grunt.loadNpmTasks( 'grunt-version' );

};
