import { useState } from "react";
import { Menu, X, Search, Bell, User, Heart } from "lucide-react";

const Navbar = ({ onBusca }) => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [termoBusca, setTermoBusca] = useState("");

  const handleBusca = (e) => {
    e.preventDefault();
    if (onBusca) {
      const termo = termoBusca ? termoBusca.trim() : '';
      onBusca(termo);
    }
  };

  return (
    <nav className="bg-white shadow-lg sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          <div className="flex items-center space-x-2">
            <div className="bg-gradient-to-br from-primary-600 to-secondary-600 p-2 rounded-lg">
              <svg
                className="w-8 h-8 text-white"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div>
              <h1 className="text-2xl font-bold bg-gradient-to-r from-primary-600 to-secondary-600 bg-clip-text text-transparent">
                EconomizAI
              </h1>
              <p className="text-xs text-gray-500">Melhores ofertas, sempre!</p>
            </div>
          </div>

          <div className="hidden md:flex flex-1 max-w-2xl mx-8">
            <form onSubmit={handleBusca} className="relative w-full">
              <input
                type="text"
                value={termoBusca}
                onChange={(e) => setTermoBusca(e.target.value)}
                placeholder="Buscar produtos em oferta..."
                className="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-full focus:outline-none focus:border-primary-500 transition-all"
              />
              <button
                type="submit"
                className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary-600"
              >
                <Search className="w-5 h-5" />
              </button>
            </form>
          </div>

          <div className="hidden md:flex items-center space-x-6">
            <button className="relative text-gray-600 hover:text-primary-600 transition-colors">
              <Heart className="w-6 h-6" />
              <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                3
              </span>
            </button>

            <button className="relative text-gray-600 hover:text-primary-600 transition-colors">
              <Bell className="w-6 h-6" />
              <span className="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                5
              </span>
            </button>

            <button className="flex items-center space-x-2 text-gray-600 hover:text-primary-600 transition-colors">
              <User className="w-6 h-6" />
              <span className="font-medium">Entrar</span>
            </button>
          </div>

          <button
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            className="md:hidden text-gray-600"
          >
            {isMenuOpen ? (
              <X className="w-6 h-6" />
            ) : (
              <Menu className="w-6 h-6" />
            )}
          </button>
        </div>

        <div className="md:hidden pb-4">
          <form onSubmit={handleBusca} className="relative">
            <input
              type="text"
              value={termoBusca}
              onChange={(e) => setTermoBusca(e.target.value)}
              placeholder="Buscar ofertas..."
              className="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-500"
            />
            <button
              type="submit"
              className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"
            >
              <Search className="w-5 h-5" />
            </button>
          </form>
        </div>
      </div>

      {/* Mobile Menu */}
      {isMenuOpen && (
        <div className="md:hidden bg-white border-t">
          <div className="px-4 py-3 space-y-3">
            <button className="flex items-center space-x-3 w-full text-left py-2 text-gray-700 hover:text-primary-600">
              <Heart className="w-5 h-5" />
              <span>Favoritos (3)</span>
            </button>
            <button className="flex items-center space-x-3 w-full text-left py-2 text-gray-700 hover:text-primary-600">
              <Bell className="w-5 h-5" />
              <span>Notificações (5)</span>
            </button>
            <button className="flex items-center space-x-3 w-full text-left py-2 text-gray-700 hover:text-primary-600">
              <User className="w-5 h-5" />
              <span>Minha Conta</span>
            </button>
          </div>
        </div>
      )}
    </nav>
  );
};

export default Navbar;
