This is the development repository of a demo plugin for WordPress built using the Saltus Framework for WordPress.

The following are needed to work with this project:
 1.   [Composer](https://getcomposer.org/) to manage PHP dependencies
 2.   [Node.js](https://nodejs.org/en/) to handle release tasks using [grunt](https://gruntjs.com/)

## How to use this Demo Plugin to create your own:

1. Download, fork or clone this repository
2. Make the necessary name changes to the folder and main file ( framework-demo.php )
3. Edit the header of the main file, so that the plugin has the proper information
4. Replace the text domain used by the plugin ( You can do a find/replace for `‘framework-demo’` )
5. Replace Namespace used (optional) ( You can do a find/replace for `PluginFrameworkDemo` for example)
6. Optionally create a git repository for your project

## To start development:

1.  Open the build folder in the terminal and run: `composer install` and `npm install`
2.  And now the fun part: edit the files in src/models to create your own model. Check [Saltus Framework Documentation](https://github.com/SaltusDev/saltus-framework) to understand how to use models.

We are working on providing a more simple way to start using this demo for less experienced developers.


## Available Grunt Tasks

All grunt tasks run inside the build folder.

**Main Release Tasks:**


`grunt bump` - Will do a minor increase in the plugin version


`grunt release` - Runs multiple tasks to prepare your plugin for release, creating in the end a release folder and zip file inside the build/release folder. These files should be ready for distribution.


**Other Development tasks:**
`grunt bs` - browser sync
`grunt i18n` - internationalization
`grunt build` - compiles files
`grunt dev` - compiles files
`grunt prod` - compiles files
