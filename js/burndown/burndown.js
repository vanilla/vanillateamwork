
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

        burndown.week = $('.js-burndown.is-burndown').first().data('burndownid');
        console.log('burndown week: '+burndown.week);

        // Active
        active.start('burndown', '/burndown/burndowndata.json/' + burndown.week, 120, true);

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

        // Force refresh
        active.refresh();

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
            tooltips: function(env, series, index, value, label) {

                var eventKey = env.analytics.data.events.keys[index];
                if (!env.analytics.data.events.series.hasOwnProperty(eventKey)) {
                    env.analytics.widget.find('.scope-tooltips').html('');
                    return null;
                }

                var events = env.analytics.data.events.series[eventKey];
                var eventDate = events.date;
                var eventList = '';

                var totalHours = 0;
                jQuery.each(events.events, function(projectid, project){
                    var hours = Math.round(project.minutes / 60,1);
                    totalHours += hours;
                    var hoursText = (hours === 1) ? 'hour' : 'hours';
                    eventList += "<div class=\"event\">\n\
    <span class=\"project-name\">"+project.project+"</span> (<span class=\"project-info\"><span class=\"project-hours\">"+hours+" "+hoursText+"</span></span>)\
</div>";
                });

                var totalHoursText = totalHours === 1 ? 'hour' : 'hours';
                var tooltiptext = "<div class=\"chart-tooltip scope-events\">\n\
    <div class=\"tooltip-title\">scope increases</div>\n\
    <div class=\"date\">" + eventDate + "</div>\n\
    <div class=\"events\">\
        {events}\
    </div>\
    <div class=\"scope-summary\">\
        Total: <span class=\"summary-hours\">"+totalHours+" "+totalHoursText+"</span>\
    </div>\
</div>";

                env.analytics.widget.find('.scope-tooltips').html(tooltiptext.replace('{events}', eventList));

                return null;
            },
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
                },
                tooltip: {
                    height: 300,
                    width: 180,
                    padding: 0,
                    roundedCorners: 0,
                    frameProps: {
                        opacity: 1,
                        fill: null,
                        stroke: "#ffffff",
                        "stroke-width": 0
                    }
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
                    },
                    tooltip: null
                },
                todaySeries: {
                    color: "#ff9c3b",
                    fill: false,
                    axis: 'l',
                    plotProps: {
                        stroke: "#ff9c3b",
                        "stroke-width": 4,
                        "stroke-dasharray": '.'
                    },
                    tooltip: null
                }
            },
            features: {
                tooltip: {
                    positionHandler: function(env, tooltipConf, mouseAreaData, suggestedX, suggestedY) {
                        return [-180,300];
                    }
                },
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
