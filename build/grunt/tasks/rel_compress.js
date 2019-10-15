module.exports = function( grunt ) {
	grunt.config('compress', {
		build: {
			options: {
				archive: 'release/<%= pkg.name %>-<%= pkg.version %>.zip'
			},
			cwd: '../dist',
			expand: true,
			src: [
				'**/*',
			],
		}
	} );

	grunt.loadNpmTasks('grunt-contrib-compress');
}