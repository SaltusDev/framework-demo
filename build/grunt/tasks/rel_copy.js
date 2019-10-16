module.exports = function( grunt ) {

	grunt.config( 'copy', {
		release: {
			files: [
				// includes all production files
				{
					expand: true,
					cwd: '../',
					src: [
						'src/**/*',
						'languages/**/*',
						'<%= pkg.name %>.php',
						'index.php',
						'LICENSE.txt',
						'vendor/**/*'
					],
					dest: 'dist/<%= pkg.name %>/' },

			],
		},
	} );

	grunt.loadNpmTasks('grunt-contrib-copy');

};
