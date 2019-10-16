module.exports = function( grunt ) {
	grunt.config('archive', {
		release: {
			options: {
				archive: 'release/<%= pkg.name %>-<%= pkg.version %>.zip'
			},
			cwd: 'dist/',
			expand: true,
			src: [
				'<%= pkg.name %>/**/*',
			],
		}
	} );

	grunt.loadNpmTasks('grunt-contrib-compress');
	grunt.renameTask('compress', 'archive' );
}