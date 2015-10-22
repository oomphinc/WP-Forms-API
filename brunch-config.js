exports.config = {
	files: {
		javascripts: {
			joinTo: {
				"js/main.js": /^(src\/js)/,
				"js/vendor.js": /^(bower_components)/
			}
		},
		stylesheets: {
			joinTo: "css/styles.css"
		},
	},
	paths: {
		watched: [
			"src/scss/",
			"src/js/"
		],
		public: "web/app/themes/@@PROJECT_SLUG@@/assets"
	},
	plugins: {
		babel: {
			ignore: [/^(bower_components)/],
			pattern: /\.(js|jsx)/
		}
	},
	modules: {
		nameCleaner: function (path) {
			return path.replace(/src\/js\//, '');
		}
	}
};
