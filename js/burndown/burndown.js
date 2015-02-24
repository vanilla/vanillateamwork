
/**
 * Teamwork / Burndown Javascript
 *
 * This javascript runs on the burndown overview page and is responsible for
 * gathering and making available information about the burndown's tasks and
 * rendering graphs for that data.
 */

var burndown = {
    week: null,
    type: null,
    analytics: [],

    start: function(method) {

        // Active
        burndown.week = $('.js-burndown.is-burndown').first().data('week');
        active.start('burndown', '/burndown/' + method + '.json/' + burndown.week);

        // Charts
        jQuery('.js-burndown.is-burndown .is-chart').each(function(i, element) {
            var widget = jQuery(element);
            var chartwidget = jQuery(element).find('.chart-widget').first();

            var chartoptions = {};
            chartoptions.tag = widget.data('tag') || false;
            chartoptions.url = gdn.url(widget.data('url'));
            chartoptions.refresh = widget.data('refresh') || false;
            if (chartoptions.refresh) {
                chartoptions.interval = parseInt(widget.data('interval')) || 30;
            }
            chartoptions.template = burndown.loadBurndownTemplate;

            // Initialize chart
            var burndownanalytics = new analytics();
            burndownanalytics.init(chartwidget, chartoptions);

            burndown.analytics.push(burndownanalytics);
        });

    },

    /**
     * Load a template for this gridwidth
     *
     * @param {String} tag
     * @param {Integer} gridWidth
     * @returns {Boolean}
     */
    loadBurndownTemplate: function(tag, gridWidth) {
        var analyticsTplName = null;
        var tweaks = {};
        switch (tag) {
            case 'burndown':
                analyticsTplName = "teamwork/burndown";
                break;
        }

        var realTplName = analyticsTplName + '/' + gridWidth;

        // Have we already loaded a compatible template?
        if (graphing.haveTemplate(realTplName)) {
            return realTplName;
        }

        // Load new TPL based on base TPL name
        var alterations = burndown.templates[analyticsTplName];
        jQuery.extend(true, alterations, tweaks);
        graphing.loadTemplate(realTplName, burndown.templates[analyticsTplName].base, alterations);

        return realTplName;
    },

    templates: {

        // Burndown

        "teamwork/burndown": {
            base: "bluePrecision",
            axis: {
                x: {
                    labelsFormatHandler: function(label, index) {
                        return label;
                    }
                },
                l: {
                    labelsDistance: -10,
                    labelsProps: {
                        fill: "white",
                        "font-size": "11px",
                        "font-weight": "bold"
                    }
                },
                floating: {
                    labels: false
                }
            },
            defaultSeries: {
                color: "#88ee88",
                fill: true,
                hideNulls: true,
                plotProps: {
                    opacity: 1,
                    "stroke-width": 1,
                    stroke: "#88ee88"
                },
                dotProps: {
                    stroke: "white",
                    size: 0,
                    "stroke-width": 0
                }
            },
            series: {
                burndownSeries: {
                    color: "#67b6ff",
                    fill: false,
                    axis: 'l',
                    plotProps: {
                        "stroke-width": 4,
                        stroke: "#67b6ff"
                    }
                },
                todaySeries: {
                    color: "#ff9c3b",
                    fill: false,
                    axis: 'l',
                    plotProps: {
                        stroke: "#ff9c3b",
                        "stroke-width": 4,
                        "stroke-dasharray": '.'
                    }
                }
            },
            features: {
                grid: {
                    forceBorder: [false, false, true, true],
                    nx: 5,
                    ny: 6,
                    props: {
                        stroke: "#346490"
                    }
                }
            }
        }
    }
};
