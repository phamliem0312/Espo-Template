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

define('advanced:views/report/reports/charts/grid1bar-horizontal', 'advanced:views/report/reports/charts/grid1bar-vertical', function (Dep) {

    return Dep.extend({

        noLegend: true,

        rowHeight: 25,

        zooming: false,

        init: function () {
            Dep.prototype.init.call(this);
        },

        calculateHeight: function () {
            var number = this.grList.length;
            if (this.columnList && this.columnList.length > 1) {
                number *= this.columnList.length;
            }

            return number * this.rowHeight;
        },

        prepareData: function () {
            var result = this.result;
            var grList = this.grList = Espo.Utils.clone(result.grouping[0]);
            grList.reverse();

            if (this.options.color) {
                this.colorList = Espo.Utils.clone(this.colorList);
                this.colorList[0] = this.options.color;
            }

            var columnList = this.columnList || [this.column];

            var baseShift, middleIndex;

            if (this.columnList) {
                this.barWidth = 1 / (this.columnList.length) * 0.65;

                var baseShift = 1 / this.columnList.length;
                var middleIndex = Math.ceil(this.columnList.length / 2) - 1;

                this.noLegend = false;
            }

            var max = 0;
            var max2 = 0;

            var min = 0;
            var min2 = 0;

            var chartData = [];

            columnList.forEach(function (column, j) {
                var columnData = {
                    data: [],
                    label: this.reportHelper.formatColumn(column, this.result),
                    column: column,
                };

                var shift = 0;

                if (this.columnList) {
                    var diffIndex = j - middleIndex;

                    shift = baseShift * diffIndex;

                    if (this.columnList.length % 2 === 0) {
                        shift -= baseShift / 2;
                    }

                    shift *= 0.75;

                    if (this.secondColumnList && ~this.secondColumnList.indexOf(column)) {
                        columnData.xaxis = 2;
                    }
                }

                grList.forEach(function (group, i) {
                    var value = (this.result.reportData[group] || {})[column] || 0;

                    if (this.secondColumnList && ~this.secondColumnList.indexOf(column)) {
                        if (value > max2) {
                            max2 = value;
                        }

                        if (value < min2) {
                            min2 = value;
                        }
                    } else {
                        if (value > max) {
                            max = value;
                        }

                        if (value < min) {
                            min = value;
                        }
                    }

                    columnData.data.push([
                        value, i - shift
                    ]);
                }, this);

                if (column in this.colors) {
                    columnData.color = this.colors[column];
                }

                chartData.push(columnData);
            }, this);

            this.max = max;
            this.max2 = max2;

            this.min = min;
            this.min2 = min2;

            this.chartData = chartData;
        },

        getTickNumber: function () {
            var containerHeight = this.$container.height();
            var tickNumber = Math.floor(containerHeight / this.rowHeight);

            return tickNumber;
        },

        draw: function () {
            if (this.$container.height() === 0) {
                this.$container.empty();

                return;
            }

            if (this.isNoData()) {
                this.showNoData();

                return;
            }

            if (this.$container.height() === 0) {
                return;
            }

            var tickNumber = this.getTickNumber();

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                bars: {
                    show: true,
                    horizontal: true,
                    shadowSize: 0,
                    lineWidth: 1,
                    fillOpacity: 1,
                    barWidth: this.barWidth,
                },
                grid: {
                    horizontalLines: false,
                    verticalLines: true,
                    outline: 'sw',
                    color: this.gridColor,
                    tickColor: this.tickColor,
                },
                yaxis: {
                    min: 0,
                    noTicks: 10,
                    color: this.textColor,
                    noTicks: tickNumber,
                    title: '&nbsp;',
                    tickFormatter: function (value) {
                        if (value % 1 == 0) {
                            var i = parseInt(value);

                            if (i in this.grList) {
                                return this.formatGroup(0, this.grList[i]);
                            }
                        }
                        return '';
                    }.bind(this)
                },
                xaxis: {
                    min: this.min + 0.08 * this.min,
                    showLabels: true,
                    color: this.textColor,
                    max: this.max + 0.08 * this.max,
                    tickFormatter: function (value) {
                        if (value == 0 && this.min === 0) {
                            return '';
                        }

                        if (value % 1 == 0) {
                            if (value > this.max + 0.05 * this.max) {
                                return '';
                            }

                            return this.formatNumber(Math.floor(value), this.isCurrency, true, true, true).toString();
                        }

                        return '';
                    }.bind(this)
                },
                x2axis: {
                    min: this.min2 + 0.08 * this.min2,
                    showLabels: false,
                    color: this.textColor,
                    max: this.max2 + 0.08 * this.max2,
                    tickFormatter: function (value) {
                        if (value == 0 && this.min2 === 0) {
                            return '';
                        }

                        if (value % 1 == 0) {
                            if (value > this.max2 + 0.05 * this.max2) {
                                return '';
                            }

                            return this.formatNumber(Math.floor(value), false, true, true).toString();
                        }

                        return '';
                    }.bind(this)
                },
                mouse: {
                    track: true,
                    relative: true,
                    position: 'w',
                    autoPositionHorizontal: true,
                    lineColor: this.hoverColor,
                    cursorPointer: true,
                    trackFormatter: function (obj) {
                        var i = obj.index;
                        var column = obj.series.column;
                        var string = this.formatGroup(0, this.grList[i]);

                        if (this.columnList) {
                            if (string) {
                                string += '<br>';
                            }

                            string += obj.series.label;
                        }

                        if (string) {
                            string += '<br>';
                        }

                        string += this.formatCellValue(obj.x, column);

                        return string;
                    }.bind(this)
                },
                legend: {
                    show: this.columnList,
                    noColumns: this.getLegendColumnNumber(),
                    container: this.$el.find('.legend-container'),
                    labelBoxMargin: 0,
                    labelFormatter: this.labelFormatter.bind(this),
                    labelBoxBorderColor: 'transparent',
                    backgroundOpacity: 0,
                },
            });

            Flotr.EventAdapter.observe(this.$container.get(0), 'flotr:click', function (position) {
                if (!position.hit) {
                    return;
                }

                if (!('index' in position.hit)) {
                    return;
                }

                var column = null;

                if (this.result.isJoint) {
                    if (this.columnList) {
                        column = this.columnList[position.hit.seriesIndex];
                    } else {
                        column = this.column;
                    }
                }

                this.trigger('click-group', this.grList[position.hit.index], undefined, undefined, column);
            }.bind(this));

            if (this.columnList) {
                this.adjustLegend();
            }
        }
    });
});
