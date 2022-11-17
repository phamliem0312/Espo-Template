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

define('advanced:views/report/reports/charts/grid1line', 'advanced:views/report/reports/charts/grid1bar-vertical', function (Dep) {

    return Dep.extend({

        noLegend: true,

        columnWidth: 80,

        isLine: true,

        zooming: true,

        pointXHalfWidth: 0,

        init: function () {
            Dep.prototype.init.call(this);
        },

        getTickNumber: function () {
            var containerWidth = this.$container.width();
            var tickNumber = Math.floor(containerWidth / this.columnWidth);
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

            var containerWidth = this.$container.width();
            var stripTicks = false;
            var tickNumber = this.getTickNumber();
            var pointCount = this.getDisplayedPointCount();

            var verticalLineNumber = pointCount;
            if (containerWidth / pointCount < this.columnWidth) {
                verticalLineNumber = tickNumber;
            } else {
                if (pointCount > tickNumber) {
                    stripTicks = true;
                    var tickDelta = Math.floor(pointCount / tickNumber);
                }
            }

            this.$graph = this.flotr.draw(this.$container.get(0), this.chartData, {
                shadowSize: false,
                colors: this.colorList,
                lines: {
                    show: true,
                    lineWidth: 3,
                    fill: !this.columnList,
                },
                points: {
                    show: false,
                },
                grid: {
                    horizontalLines: true,
                    verticalLines: true,
                    outline: 'sw',
                    color: this.gridColor,
                    tickColor: this.tickColor
                },
                yaxis: {
                    min: this.min + 0.08 * this.min,
                    showLabels: true,
                    autoscale: true,
                    autoscaleMargin: 0.1,
                    color: this.textColor,
                    max: this.max + 0.08 * this.max,
                    tickFormatter: function (value) {
                        if (value > this.max + 0.09 * this.max) {
                            return '';
                        }

                        if (
                            (value != 0 || value == 0 && this.min < 0)
                            &&
                            value % 1 == 0
                        ) {
                            return this.formatNumber(Math.floor(value), this.isCurrency, true, true, true).toString();
                        }

                        return '';
                    }.bind(this)
                },
                y2axis: {
                    min: this.min2 + 0.08 * this.min2,
                    showLabels: true,
                    color: this.textColor,
                    max: this.max2 + 0.08 * this.max2,
                    tickFormatter: function (value) {
                        if (value == 0 && this.min2 === 0) {
                            return '';
                        }

                        if (value > this.max2 + 0.07 * this.max2) {
                            return '';
                        }

                        if (value % 1 == 0) {
                            return this.formatNumber(Math.floor(value)).toString();
                        }

                        return '';
                    }.bind(this)
                },
                xaxis: {
                    min: this.xMin || 0,
                    max: this.xMax || null,
                    color: this.textColor,
                    noTicks: verticalLineNumber,
                    tickFormatter: function (value) {
                        if (value % 1 == 0) {
                            var i = parseInt(value);

                            if (stripTicks) {
                                if (i % tickDelta !== 0) {
                                    return '';
                                }
                            }

                            if (i === 0) {
                                return '';
                            }

                            if (i in this.grList) {
                                if (this.grList.length > 4 && i === this.grList.length - 1) {
                                    return '';
                                }

                                if (i === this.grList.length - 1) {
                                    return '';
                                }
                                return this.formatGroup(0, this.grList[i]);
                            }
                        }

                        return '';
                    }.bind(this)
                },
                mouse: {
                    track: true,
                    relative: true,
                    lineColor: this.hoverColor,
                    autoPositionHorizontal: true,
                    cursorPointer: true,
                    trackFormatter: function (obj) {
                        var i = Math.floor(obj.x);

                        var column = obj.series.column;
                        var string = this.formatGroup(0, this.grList[i]);

                        if (this.columnList) {
                            string += '<br>' + obj.series.label;
                        }

                        string += '<br>' + this.formatCellValue(obj.y, column);

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

            if (this.columnList) {
                this.adjustLegend();
            }

            if (this.dragStart) {
                return;
            }

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
        },
    });
});
