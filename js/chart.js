document.addEventListener( 'DOMContentLoaded', function() {
    const ctx = document.getElementById( 'lcStatsChart' ).getContext( '2d' );
    let chart;

    const latestDate = new Date( chartData.latestDate * 1000 );
    let labels = [];
    for ( let i = 0; i < 7; i++ ) {
        const date = new Date( latestDate );
        date.setDate( latestDate.getDate() - i );

        switch ( i ) {
            case 0:
                labels.push( 'Today (' + date.toLocaleDateString() + ')' );
                break;
            case 1:
                labels.push( 'Yesterday (' + date.toLocaleDateString() + ')' );
                break;
            default:
                labels.push( i + ' days ago (' + date.toLocaleDateString() + ')' );
                break;
        }
    }
    labels = labels.reverse();

    const comment_counts = chartData.comment_counts;
    const reply_counts = chartData.reply_counts;

    function createChart() {
        if ( chart ) {
            chart.destroy();
        }

        chart = new Chart( ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Comments',
                    data: comment_counts,
                    borderColor: '#0075ff',
                    backgroundColor: '#0075ff',
                    borderRadius: 7,
                    fill: false
                }, {
                    label: 'Replies',
                    data: reply_counts,
                    borderColor: '#ff5722',
                    backgroundColor: '#ff5722',
                    borderRadius: 7,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                title: {
                    display: true,
                    text: 'Daily Comment and Reply Counts'
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                },
                hover: {
                    mode: 'nearest',
                    intersect: true
                },
                scales: {
                    x: {
                        display: window.innerWidth > 1024,
                    },
                    y: {
                        display: true,
                        beginAtZero: true,
                        ticks: {
                            callback: function( value ) {
                                if ( Math.floor( value ) === value ) {
                                    return value;
                                }
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    }

    createChart();

    window.addEventListener( 'resize', createChart );
} );