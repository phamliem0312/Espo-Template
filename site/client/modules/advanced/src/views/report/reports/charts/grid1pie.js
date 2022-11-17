/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

Espo.define('advanced:views/report/reports/charts/grid1pie', 'advanced:views/report/reports/charts/grid1bar-vertical', function (Dep) {

    return Dep.extend({

        noLegend: false,

        zooming: false,

        prepareData: function () {
            var result = this.result;
            var grList = this.grList = result.grouping[0];

            if (grList.length > 5) {
                this.colorList = this.colorList;
            } else {
                this.colorList = this.colorListAlt;
            }

            var data = [];
            this.values = [];

            grList.forEach(function (gr, i) {
                var value = (this.result.reportData[gr] || {})[this.column] || 0;
                this.values.push(value);

                var o = {
                    label: this.formatGroup(0, gr),
                    groupValue: gr,
                    data: [[0, value]],
                    value: value
                };

                if (gr in this.colors) {
                    o.color = this.colors[gr];
                }

                data.push(o);

            }, this);

            this.chartData = data;
        },

        isNoData: function () {
            return !this.chartData.length;
        },

        draw: function () {
            if (this.$container.height() === 0) {
                this.$container.empty();
                return;
            }

            var self = this;

            if (this.isNoData()) {
                this.showNoData();
                return;
            }

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                pie: {
                    show: true,
                    fillOpacity: 1,
                    explode: 0,
                    lineWidth: 1,
                    sizeRatio: 0.75,
                    labelFormatter: function (total, value) {
                        var percentage = (100 * value / total).toFixed(2);
                        if (percentage < 7) return '';
                        return '<span class="" style="font-size: 0.8em;color:'+this.textColor+'">'+ percentage.toString() +'%' + '</span>';
                    }.bind(this)
                },
                grid: {
                    horizontalLines: false,
                    verticalLines: false,
                    outline: '',
                    color: this.gridColor
                },
                yaxis: {
                    showLabels: false
                },
                xaxis: {
                    showLabels: false
                },
                mouse: {
                    track: true,
                    relative: true,
                    lineColor: this.hoverColor,
                    cursorPointer: true,
                    trackFormatter: function (obj) {
                        var column = self.options.column;
                        var value = self.formatCellValue(obj.series.value, column);

                        var fraction = obj.fraction || 0;
                        var percentage = (100 * fraction).toFixed(2).toString();

                        return (obj.series.label || self.translate('-Empty-', 'labels', 'Report')) + '<br>' + value + ' / ' + percentage + '%';
                    }
                },
                legend: {
                    show: true,
                    noColumns: this.getLegendColumnNumber(),
                    container: this.$el.find('.legend-container'),
                    labelBoxMargin: 0,
                    labelFormatter: self.labelFormatter.bind(self),
                    labelBoxBorderColor: 'transparent',
                    backgroundOpacity: 0
                }
            });

            Flotr.EventAdapter.observe(this.$container.get(0), 'flotr:click', function (position) {
                if (!position.hit) return;
                if (!('index' in position.hit)) return;
                this.trigger('click-group', position.hit.series.groupValue);
            }.bind(this));

            this.adjustLegend();
        }
    });
});
