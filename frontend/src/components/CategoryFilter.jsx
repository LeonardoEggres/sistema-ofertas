import { Smartphone, Laptop, Tv, Gamepad2, Book, Dumbbell } from 'lucide-react';

const CategoryFilter = ({ selectedCategory, onSelectCategory }) => {
  const categories = [
    { id: 'all', name: 'Todas', icon: null, color: 'bg-gray-600' },
    { id: 'MLB1055', name: 'Celulares', icon: Smartphone, color: 'bg-blue-600' },
    { id: 'MLB1196', name: 'Notebooks', icon: Laptop, color: 'bg-purple-600' },
    { id: 'MLB1002', name: 'TVs', icon: Tv, color: 'bg-red-600' },
    { id: 'MLB1144', name: 'Games', icon: Gamepad2, color: 'bg-green-600' },
    { id: 'MLB1384', name: 'Livros', icon: Book, color: 'bg-yellow-600' },
    { id: 'MLB1430', name: 'Esportes', icon: Dumbbell, color: 'bg-orange-600' },
  ];

  return (
    <div className="bg-white rounded-xl shadow-md p-6 mb-8">
      <h2 className="text-lg font-bold text-gray-900 mb-4">Categorias</h2>
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
        {categories.map((category) => {
          const Icon = category.icon;
          const isSelected = selectedCategory === category.id;
          
          return (
            <button
              key={category.id}
              onClick={() => onSelectCategory(category.id)}
              className={`
                flex flex-col items-center p-4 rounded-lg transition-all duration-200
                ${isSelected 
                  ? `${category.color} text-white shadow-lg scale-105` 
                  : 'bg-gray-50 text-gray-700 hover:bg-gray-100'
                }
              `}
            >
              {Icon && <Icon className="w-8 h-8 mb-2" />}
              {!Icon && (
                <div className="w-8 h-8 mb-2 flex items-center justify-center">
                  <span className="text-2xl">📦</span>
                </div>
              )}
              <span className="text-sm font-medium text-center">{category.name}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
};

export default CategoryFilter;