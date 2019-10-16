module.exports = function( grunt ) {

	grunt.config( 'gittag', {
		release: {
			options: {
				tag: '<%= pkg.version %>',
				message: 'Tagging version <%= pkg.version %>',
			}
		}
	} );

	grunt.loadNpmTasks( 'grunt-git' );

};
