module.exports = function(grunt) {

	grunt.config( ['composer'], {
		release: {
			options: {
				flags: ['no-dev' ],
			},
		},
	});

	grunt.loadNpmTasks('grunt-composer');

};
