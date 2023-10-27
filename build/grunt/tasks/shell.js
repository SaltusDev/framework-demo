module.exports = function(grunt) {

	grunt.config( ['shell'], {
		release: {
			options: {
				stdout: true
			},
			command: 'composer install --no-dev --optimize-autoloader --ignore-platform-req=php'
		},
	});

	grunt.loadNpmTasks('grunt-shell');

};
