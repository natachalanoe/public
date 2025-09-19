/**
 * DataTable configuration for Contracts table
 * Responsive DataTable with modal details
 */

document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  // Get the contracts table
  const dt_contracts_table = document.querySelector('#contractsTable');
  
  if (dt_contracts_table) {
    // Récupérer la configuration sauvegardée
    const savedConfig = window.DataTablePersistence ? 
      window.DataTablePersistence.getTableConfig('contractsTable') : 
      { pageLength: 10, order: [[2, 'asc']] };

    let dt_contracts = new DataTable(dt_contracts_table, {
      // Configuration options avec persistance
      pageLength: savedConfig.pageLength,
      lengthMenu: [10, 25, 50, 100],
      order: savedConfig.order, // Sort by end date ascending by default
      
      // Layout configuration
      layout: {
        topStart: {
          rowClass: 'row mx-3 my-0 justify-content-between',
          features: [
            {
              pageLength: {
                menu: [10, 25, 50, 100],
                text: 'Afficher _MENU_ entrées'
              }
            }
          ]
        },
        topEnd: {
          search: {
            placeholder: 'Rechercher...'
          }
        },
        bottomStart: {
          rowClass: 'row mx-3 justify-content-between',
          features: ['info']
        },
        bottomEnd: {
          paging: {
            firstLast: false
          }
        }
      },

      // Language configuration
      language: {
        url: 'assets/json/locales/datatables-fr.json',
        paginate: {
          next: '<i class="icon-base bx bx-chevron-right scaleX-n1-rtl icon-sm"></i>',
          previous: '<i class="icon-base bx bx-chevron-left scaleX-n1-rtl icon-sm"></i>'
        }
      },

      // Responsive configuration
      responsive: {
        details: {
          display: DataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Détails du contrat ' + (data[0] || '');
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            const data = columns
              .map(function (col) {
                return col.title !== '' // Do not show row in modal popup if title is blank
                  ? `<tr data-dt-row="${col.rowIndex}" data-dt-column="${col.columnIndex}">
                      <td><strong>${col.title}:</strong></td>
                      <td>${col.data}</td>
                    </tr>`
                  : '';
              })
              .join('');

            if (data) {
              const div = document.createElement('div');
              div.classList.add('table-responsive');
              const table = document.createElement('table');
              div.appendChild(table);
              table.classList.add('table');
              table.classList.add('table-striped');
              const tbody = document.createElement('tbody');
              tbody.innerHTML = data;
              table.appendChild(tbody);
              return div;
            }
            return false;
          }
        }
      },

      // Column definitions
      columnDefs: [
        {
          // Contract name column
          targets: 0,
          responsivePriority: 1
        },
        {
          // Client column
          targets: 1,
          responsivePriority: 2
        },
        {
          // Contract type column
          targets: 2,
          responsivePriority: 3
        },
        {
          // End date column
          targets: 3,
          responsivePriority: 4
        },
        {
          // Remaining tickets column
          targets: 4,
          responsivePriority: 5
        },
        {
          // Status column
          targets: 5,
          responsivePriority: 6
        }
      ],

      // Initialization complete callback
      initComplete: function() {
        // Add any additional initialization here
        console.log('Contracts DataTable initialized');
      },

      // Callbacks pour la persistance
      drawCallback: function(settings) {
        // Sauvegarder la configuration actuelle
        if (window.DataTablePersistence) {
          window.DataTablePersistence.saveTableConfig('contractsTable', {
            pageLength: settings._iDisplayLength,
            order: settings.aaSorting,
            page: settings._iDisplayStart / settings._iDisplayLength
          });
        }
      }
    });
  }
}); 